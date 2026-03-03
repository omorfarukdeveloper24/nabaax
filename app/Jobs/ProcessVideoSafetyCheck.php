<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Google\Cloud\VideoIntelligence\V1\VideoIntelligenceServiceClient;
use Google\Cloud\VideoIntelligence\V1\Feature;
use App\Models\Post;
use App\Models\Post_media;
use Illuminate\Support\Facades\Log;
use \App\Traits\NotificationTrait;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use FFMpeg\Format\Video\X264;

class ProcessVideoSafetyCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NotificationTrait;

    protected $postId;
    protected $videoPath;
    protected $customThumbPath;

    public $timeout = 1800; 
    public $tries = 1;      

    public function __construct($postId, $videoPath, $customThumbPath = null)
    {
        $this->postId = $postId;
        $this->videoPath = $videoPath;
        $this->customThumbPath = $customThumbPath;
    }

    public function handle()
    {
        $post = Post::find($this->postId);
        if (!$post || !file_exists($this->videoPath)) {
            $this->cleanupFiles();
            return;
        }

        $keyFileData = config('filesystems.disks.gcs.key_file');
        $bucketName = config('filesystems.disks.gcs.bucket');
        $projectId = config('filesystems.disks.gcs.project_id');

        try {
            Log::info("Safety Check Started for Post ID: {$this->postId}");

            // ১. ভিডিও ইন্টেলিজেন্স চেক (Permission error fix করার জন্য credentials ব্যবহার করা হয়েছে)
            $videoClient = new VideoIntelligenceServiceClient(['credentials' => $keyFileData]);
            
            $operation = $videoClient->annotateVideo([
                'inputContent' => file_get_contents($this->videoPath),
                'features' => [Feature::EXPLICIT_CONTENT_DETECTION],
            ]);

            $operation->pollUntilComplete(['pollingIntervalSeconds' => 5]);

            $isSafe = true;
            if ($operation->operationSucceeded()) {
                $results = $operation->getResult()->getAnnotationResults()[0];
                $explicitAnnotation = $results->getExplicitAnnotation();

                if ($explicitAnnotation) {
                    foreach ($explicitAnnotation->getFrames() as $frame) {
                        // ৫ (Very Likely) পর্নোগ্রাফি চেক
                        if ($frame->getPornographyLikelihood() >= 5) { 
                            $isSafe = false; 
                            break;
                        }
                    }
                }
            }
            $videoClient->close();

            if (!$isSafe) {
                Log::warning("Inappropriate video deleted. Post ID: {$this->postId}");
                $post->delete();
                $this->cleanupFiles();
                return;
            }

            // ২. ফাইল পাথ সেট করা
            $fileNameBase = pathinfo($this->videoPath, PATHINFO_FILENAME);
            $videoBaseName = basename($this->videoPath);
            $compressedPath = storage_path('app/temp_videos/' . $fileNameBase . '_low.mp4');
            $thumbnailPath = storage_path('app/temp_videos/' . $fileNameBase . '_thumb.jpg');

            // ভিডিওর ডিউরেশন বের করা
            $media = FFMpeg::fromDisk('local')->open('temp_videos/' . $videoBaseName);
            $durationSeconds = $media->getDurationInSeconds();

            // ৩. থাম্বনেইল জেনারেশন (আলাদা অপারেশন হিসেবে রাখা হয়েছে এরর এড়াতে)
            if ($this->customThumbPath && file_exists($this->customThumbPath)) {
                copy($this->customThumbPath, $thumbnailPath);
            } else {
                FFMpeg::fromDisk('local')
                    ->open('temp_videos/' . $videoBaseName)
                    ->getFrameFromSeconds(min(1, $durationSeconds))
                    ->export()
                    ->toDisk('local')
                    ->save('temp_videos/' . basename($thumbnailPath));
            }

            // ৪. ভিডিও কম্প্রেশন (আলাদা ওপেন করা হয়েছে যাতে 'Not a video file' এরর না আসে)
            $format = (new X264('aac', 'libx264'))->setKiloBitrate(700); 

            FFMpeg::fromDisk('local')
                ->open('temp_videos/' . $videoBaseName)
                ->export()
                ->toDisk('local')
                ->inFormat($format)
                ->addFilter('-preset', 'ultrafast') // ২ জিবি র‍্যামের জন্য সুপার ফাস্ট
                ->addFilter('-threads', 1) 
                ->addFilter('-vf', 'scale=-2:480') // ফাস্ট প্রসেসিং রেজোলিউশন
                ->save('temp_videos/' . basename($compressedPath));

            // ৫. GCS আপলোড
            $storage = new \Google\Cloud\Storage\StorageClient([
                'projectId' => $projectId,
                'keyFile'    => $keyFileData,
            ]);
            $bucket = $storage->bucket($bucketName);

            $gcsVideoName = "posts/videos/" . basename($compressedPath);
            $gcsThumbName = "posts/thumbnails/" . basename($thumbnailPath);

            $bucket->upload(fopen($compressedPath, 'r'), ['name' => $gcsVideoName, 'resumable' => true]);
            $bucket->upload(fopen($thumbnailPath, 'r'), ['name' => $gcsThumbName]);

            // ৬. ডাটাবেজ আপডেট
            Post_media::updateOrCreate(
                ['post_id' => $post->id],
                [
                    'path' => "https://storage.googleapis.com/{$bucketName}/{$gcsVideoName}",
                    'media_type' => 'video',
                    'thumbnail_path' => "https://storage.googleapis.com/{$bucketName}/{$gcsThumbName}",
                    'duration' => round($durationSeconds),
                ]
            );

            $post->update(['status' => 'active']);
            $this->sendFcmNotification($post->member_id, "Video Ready! 🎬", "Your video is live now.");

            // ৭. ক্লিনিং
            $this->cleanupFiles($compressedPath, $thumbnailPath);

        } catch (\Exception $e) {
            Log::error("Video Job Error (Post ID {$this->postId}): " . $e->getMessage());
            $this->cleanupFiles(); // এরর হলে টেম্প ফাইল ডিলিট করে র‍্যাম খালি করা
            throw $e; 
        }
    }

    protected function cleanupFiles($compressedPath = null, $thumbnailPath = null)
    {
        if (file_exists($this->videoPath)) @unlink($this->videoPath);
        if ($compressedPath && file_exists($compressedPath)) @unlink($compressedPath);
        if ($thumbnailPath && file_exists($thumbnailPath)) @unlink($thumbnailPath);
        if ($this->customThumbPath && file_exists($this->customThumbPath)) @unlink($this->customThumbPath);
    }
}