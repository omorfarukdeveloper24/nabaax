<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use FFMpeg\Format\Video\X264;
use App\Models\Post;
use App\Models\Post_media;
use App\Traits\NotificationTrait;
use Illuminate\Support\Facades\Log;

class ProcessVideoSafetyCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NotificationTrait;

    protected $postId, $videoPath, $customThumbPath;
    public $timeout = 1800;
    public $tries = 2;

    public function __construct($postId, $videoPath, $customThumbPath = null)
    {
        $this->postId = $postId;
        $this->videoPath = $videoPath;
        $this->customThumbPath = $customThumbPath;
    }

    public function handle()
    {
        // ১. শুরুতে ডিলে (Redis Race Condition হ্যান্ডেল করতে)
        sleep(5);

        $post = Post::find($this->postId);
        
        // ফাইল আছে কি না সর্বোচ্চ ৫ বার চেক করা (২ সেকেন্ড পর পর)
        $attempts = 0;
        while (!file_exists($this->videoPath) && $attempts < 5) {
            sleep(2);
            $attempts++;
        }

        if (!$post || !file_exists($this->videoPath)) {
            Log::error("Final Failure: Video file not found at {$this->videoPath}");
            return;
        }

        $memberId = $post->member_id;
        $tempFiles = [];

        try {
            $keyFileData = config('filesystems.disks.gcs.key_file');
            
            // --- মূল পরিবর্তন এখানে: সরাসরি পাথ দিয়ে ওপেন করা ---
            $ffmpeg = FFMpeg::open($this->videoPath);
            $duration = $ffmpeg->getDurationInSeconds();

            $frameCount = ($duration > 600) ? 6 : 3;
            $isSafe = true;

            $imageAnnotator = new ImageAnnotatorClient(['credentials' => $keyFileData]);

            // ২. সেফটি চেক
            for ($i = 1; $i <= $frameCount; $i++) {
                $time = ($duration / ($frameCount + 1)) * $i;
                $frameName = "frame_{$this->postId}_{$i}.jpg";
                $framePath = storage_path('app/temp_videos/' . $frameName);
                
                $ffmpeg->getFrameFromSeconds($time)
                       ->export()
                       ->toDisk('local')
                       ->save('temp_videos/' . $frameName);

                $tempFiles[] = $framePath;

                if (file_exists($framePath)) {
                    $content = file_get_contents($framePath);
                    $response = $imageAnnotator->safeSearchDetection($content);
                    $safe = $response->getSafeSearchAnnotation();

                    if ($safe && ($safe->getAdult() >= 4 || $safe->getRacy() >= 4 || $safe->getViolence() >= 4)) {
                        $isSafe = false;
                        break;
                    }
                }
            }
            $imageAnnotator->close();

            if (!$isSafe) {
                Log::warning("Unsafe Content! Removing Post ID: {$this->postId}");
                $post->delete();
                $this->sendFcmNotification($memberId, "Post Removed ⚠️", "Your video violates community standards.");
                return;
            }

            // ৩. থাম্বনেইল ও কম্প্রেশন সেটআপ
            $fileNameBase = pathinfo($this->videoPath, PATHINFO_FILENAME);
            $compressedName = $fileNameBase . '_processed.mp4';
            $compressedPath = storage_path('app/temp_videos/' . $compressedName);
            $thumbName = $fileNameBase . '_thumb.jpg';
            $thumbnailPath = storage_path('app/temp_videos/' . $thumbName);

            if ($this->customThumbPath && file_exists($this->customThumbPath)) {
                copy($this->customThumbPath, $thumbnailPath);
            } else {
                $ffmpeg->getFrameFromSeconds(min(2, $duration))
                       ->export()
                       ->toDisk('local')
                       ->save('temp_videos/' . $thumbName);
            }
            $tempFiles[] = $thumbnailPath;

            // ৪. কম্প্রেশন (১০০০ বিটরেট)
            $format = (new X264('aac', 'libx264'))->setKiloBitrate(1000);
            $ffmpeg->export()
                ->toDisk('local')
                ->inFormat($format)
                ->addFilter('-preset', 'ultrafast')
                ->addFilter('-threads', 4)
                ->save('temp_videos/' . $compressedName);
            
            $tempFiles[] = $compressedPath;

            // ৫. GCS আপলোড
            $storage = new StorageClient([
                'projectId' => config('filesystems.disks.gcs.project_id'), 
                'keyFile' => $keyFileData
            ]);
            $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

            $bucket->upload(fopen($compressedPath, 'r'), ['name' => "posts/videos/" . $compressedName, 'resumable' => true]);
            $bucket->upload(fopen($thumbnailPath, 'r'), ['name' => "posts/thumbnails/" . $thumbName]);

            // ৬. ডাটাবেজ আপডেট
            Post_media::updateOrCreate(
                ['post_id' => $this->postId],
                [
                    'media_type' => 'video',
                    'path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/posts/videos/" . $compressedName,
                    'thumbnail_path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/posts/thumbnails/" . $thumbName,
                    'duration' => round($duration),
                ]
            );

            $post->update(['status' => 'active']);
            $this->sendFcmNotification($memberId, "Success! ✅", "Your video is now live.");

        } catch (\Exception $e) {
            Log::error("ProcessVideo Error (Post ID {$this->postId}): " . $e->getMessage());
            throw $e; 
        } finally {
            $this->cleanup($tempFiles);
        }
    }

    protected function cleanup($files = [])
    {
        if (file_exists($this->videoPath)) @unlink($this->videoPath);
        if ($this->customThumbPath && file_exists($this->customThumbPath)) @unlink($this->customThumbPath);
        foreach ($files as $file) {
            if (file_exists($file)) @unlink($file);
        }
    }
}