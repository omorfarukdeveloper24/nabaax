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

    // টাইমআউট ৩০ মিনিট (বড় ভিডিওর জন্য নিরাপদ)
    public $timeout = 1800; 
    // ৩ বার ট্রাই করবে যাতে সাময়িক কোনো এররে জবটি নষ্ট না হয়
    public $tries = 3;      
    // একবার ফেইল করলে ৩০ সেকেন্ড পর আবার চেষ্টা করবে
    public $backoff = 30;

    public function __construct($postId, $videoPath, $customThumbPath = null)
    {
        $this->postId = $postId;
        $this->videoPath = $videoPath;
        $this->customThumbPath = $customThumbPath;
    }

    public function handle()
    {
        // র‍্যাম পরিষ্কার রাখতে শুরুতে গার্বেজ কালেক্টর কল করা হলো
        gc_collect_cycles();

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

            // ১. গুগল ভিডিও ইন্টেলিজেন্স চেক
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

            // ২. পাথ সেটিংস
            $fileNameBase = pathinfo($this->videoPath, PATHINFO_FILENAME);
            $videoBaseName = basename($this->videoPath);
            $compressedPath = storage_path('app/temp_videos/' . $fileNameBase . '_processed.mp4');
            $thumbnailPath = storage_path('app/temp_videos/' . $fileNameBase . '_thumb.jpg');

            // ডিউরেশন বের করা
            $media = FFMpeg::fromDisk('local')->open('temp_videos/' . $videoBaseName);
            $durationSeconds = $media->getDurationInSeconds();

            // ৩. থাম্বনেইল জেনারেশন
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

            // ৪. ভিডিও কম্প্রেশন (Medium Quality & Fast Speed)
            // setPasses(1) দেওয়া হয়েছে যাতে একবারেই প্রসেসিং শেষ হয় (Attempted error সমাধান করবে)
            $format = (new X264('aac', 'libx264'))
                        ->setKiloBitrate(1500) // ৭০০ থেকে বাড়িয়ে ১৫০০ করা হলো (Medium Quality)
                        ->setPasses(1); 

            FFMpeg::fromDisk('local')
                ->open('temp_videos/' . $videoBaseName)
                ->export()
                ->toDisk('local')
                ->inFormat($format)
                ->addFilter('-preset', 'veryfast') // দ্রুত কিন্তু ভালো কোয়ালিটি
                ->addFilter('-threads', 2)         // ২ জিবি র‍্যামের জন্য ২ থ্রেড নিরাপদ
                ->addFilter('-vf', 'scale=-2:720') // কোয়ালিটি বাড়াতে ৭২০পি এইচডি করা হলো
                ->save('temp_videos/' . basename($compressedPath));

            // ৫. GCS আপলোড
            $storage = new \Google\Cloud\Storage\StorageClient([
                'projectId' => $projectId,
                'keyFile'    => $keyFileData,
            ]);
            $bucket = $storage->bucket($bucketName);

            $gcsVideoName = "posts/videos/" . basename($compressedPath);
            $gcsThumbName = "posts/thumbnails/" . basename($thumbnailPath);

            // বড় ভিডিওর জন্য resumable আপলোড চালু রাখা হয়েছে
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
            $this->sendFcmNotification($post->member_id, "Your video is live! 🎬", "Your video has been processed and is ready to view.");

            // ৭. ক্লিয়ারেন্স
            $this->cleanupFiles($compressedPath, $thumbnailPath);

        } catch (\Exception $e) {
            Log::error("Video Job Error (Post ID {$this->postId}): " . $e->getMessage());
            $this->cleanupFiles();
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