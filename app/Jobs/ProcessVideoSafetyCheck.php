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
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProcessVideoSafetyCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NotificationTrait;

    protected $postId;
    protected $videoPath;
    protected $customThumbPath;

    public $timeout = 1800; 
    public $tries = 3;      
    public $backoff = 60;

    public function middleware()
    {
        return [(new WithoutOverlapping($this->postId))->releaseAfter(60)];
    }

    public function __construct($postId, $videoPath, $customThumbPath = null)
    {
        $this->postId = $postId;
        $this->videoPath = $videoPath;
        $this->customThumbPath = $customThumbPath;
    }

    public function handle()
    {
        gc_collect_cycles();

        $post = Post::find($this->postId);
        if (!$post || $post->status === 'active' || !file_exists($this->videoPath)) {
            $this->cleanupFiles();
            return;
        }

        $keyFileData = config('filesystems.disks.gcs.key_file');
        $bucketName = config('filesystems.disks.gcs.bucket');
        $projectId = config('filesystems.disks.gcs.project_id');

        try {
            // ১. পাথ এবং ফাইল সেটিংস
            $fileNameBase = pathinfo($this->videoPath, PATHINFO_FILENAME);
            $videoBaseName = basename($this->videoPath);
            $compressedPath = storage_path('app/temp_videos/' . $fileNameBase . '_processed.mp4');
            $thumbnailPath = storage_path('app/temp_videos/' . $fileNameBase . '_thumb.jpg');

            $media = FFMpeg::fromDisk('local')->open('temp_videos/' . $videoBaseName);
            $durationSeconds = $media->getDurationInSeconds();

            // ২. থাম্বনেইল এবং ভিডিও কম্প্রেশন (আগে প্রসেসিং)
            $this->generateThumbnail($videoBaseName, $durationSeconds, $thumbnailPath);
            $this->compressVideo($videoBaseName, $compressedPath);

            // ৩. GCS-এ ভিডিও আপলোড (সেফটি চেকের আগে)
            $storage = new \Google\Cloud\Storage\StorageClient([
                'projectId' => $projectId,
                'keyFile'    => $keyFileData,
            ]);
            $bucket = $storage->bucket($bucketName);
            
            $gcsVideoPath = "posts/videos/" . basename($compressedPath);
            $gcsThumbPath = "posts/thumbnails/" . basename($thumbnailPath);

            // ভিডিওটি GCS-এ আপলোড হচ্ছে
            $bucket->upload(fopen($compressedPath, 'r'), ['name' => $gcsVideoPath, 'resumable' => true]);
            $bucket->upload(fopen($thumbnailPath, 'r'), ['name' => $gcsThumbPath]);

            // ৪. গুগল সেফটি চেক (GCS URI ব্যবহার করে - টিকটক স্টাইল)
            Log::info("Safety Check Started via GCS URI for Post ID: {$this->postId}");
            
            $videoClient = new VideoIntelligenceServiceClient(['credentials' => $keyFileData]);
            $gcsUri = "gs://{$bucketName}/{$gcsVideoPath}"; // এটিই মূল ট্রিক

            $operation = $videoClient->annotateVideo([
                'inputUri' => $gcsUri, // ফাইল কন্টেন্ট না পাঠিয়ে লিঙ্ক পাঠানো হলো
                'features' => [Feature::EXPLICIT_CONTENT_DETECTION],
            ]);

            // এটি এখন অনেক দ্রুত হবে কারণ ফাইল অলরেডি গুগল সার্ভারে আছে
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

            // ৫. রেজাল্ট অনুযায়ী ব্যবস্থা
            if (!$isSafe) {
                Log::warning("Inappropriate video detected on GCS. Deleting... Post ID: {$this->postId}");
                $bucket->object($gcsVideoPath)->delete(); // GCS থেকে ডিলিট
                $bucket->object($gcsThumbPath)->delete();
                $post->delete();
                $this->cleanupFiles($compressedPath, $thumbnailPath);
                return;
            }

            // ৬. ডাটাবেজ আপডেট এবং একটিভ করা
            Post_media::updateOrCreate(
                ['post_id' => $post->id],
                [
                    'path' => "https://storage.googleapis.com/{$bucketName}/{$gcsVideoPath}",
                    'media_type' => 'video',
                    'thumbnail_path' => "https://storage.googleapis.com/{$bucketName}/{$gcsThumbPath}",
                    'duration' => round($durationSeconds),
                ]
            );

            $post->update(['status' => 'active']);
            $this->sendFcmNotification($post->member_id, "Your video is live! 🎬", "Processed successfully.");

            $this->cleanupFiles($compressedPath, $thumbnailPath);

        } catch (\Exception $e) {
            Log::error("Video Job Error (Post ID {$this->postId}): " . $e->getMessage());
            $this->cleanupFiles();
            throw $e; 
        }
    }

    protected function generateThumbnail($videoBaseName, $durationSeconds, $thumbnailPath) {
        if ($this->customThumbPath && file_exists($this->customThumbPath)) {
            copy($this->customThumbPath, $thumbnailPath);
        } else {
            FFMpeg::fromDisk('local')->open('temp_videos/' . $videoBaseName)
                ->getFrameFromSeconds(min(1, $durationSeconds))
                ->export()->toDisk('local')->save('temp_videos/' . basename($thumbnailPath));
        }
    }

    protected function compressVideo($videoBaseName, $compressedPath) {
        $format = (new X264('aac', 'libx264'))->setKiloBitrate(1200)->setPasses(1); 
        FFMpeg::fromDisk('local')->open('temp_videos/' . $videoBaseName)
            ->export()->toDisk('local')->inFormat($format)
            ->addFilter('-preset', 'veryfast')
            ->addFilter('-threads', 2)
            ->addFilter('-vf', 'scale=-2:720') 
            ->save('temp_videos/' . basename($compressedPath));
    }

    protected function cleanupFiles($compressedPath = null, $thumbnailPath = null)
    {
        if (file_exists($this->videoPath)) @unlink($this->videoPath);
        if ($compressedPath && file_exists($compressedPath)) @unlink($compressedPath);
        if ($thumbnailPath && file_exists($thumbnailPath)) @unlink($thumbnailPath);
        if ($this->customThumbPath && file_exists($this->customThumbPath)) @unlink($this->customThumbPath);
    }
}