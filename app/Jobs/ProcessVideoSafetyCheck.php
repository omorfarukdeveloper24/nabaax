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
        $post = Post::find($this->postId);
        if (!$post || !file_exists($this->videoPath)) {
            $this->cleanup([]);
            return;
        }

        $memberId = $post->member_id;
        $tempFiles = [];

        try {
            $keyFileData = config('filesystems.disks.gcs.key_file');
            $videoBaseName = basename($this->videoPath);
            
            $ffmpeg = FFMpeg::fromDisk('local')->open('temp_videos/' . $videoBaseName);
            $duration = $ffmpeg->getDurationInSeconds();

            $frameCount = ($duration > 600) ? 6 : 3;
            $isSafe = true;

            $imageAnnotator = new ImageAnnotatorClient(['credentials' => $keyFileData]);

            // ১. সেফটি চেক লুপ
            for ($i = 1; $i <= $frameCount; $i++) {
                $time = ($duration / ($frameCount + 1)) * $i;
                $frameName = "frame_{$this->postId}_{$i}.jpg";
                $framePath = storage_path('app/temp_videos/' . $frameName);
                
                // এখানে সঠিক export মেথড ব্যবহার করা হয়েছে
                $ffmpeg->getFrameFromSeconds($time)
                       ->export()
                       ->toDisk('local')
                       ->save('temp_videos/' . $frameName);
                
                $tempFiles[] = $framePath;

                if (file_exists($framePath)) {
                    $content = file_get_contents($framePath);
                    $response = $imageAnnotator->safeSearchDetection($content);
                    $safe = $response->getSafeSearchAnnotation();

                    if ($safe->getAdult() >= 4 || $safe->getRacy() >= 4 || $safe->getViolence() >= 4) {
                        $isSafe = false;
                        break;
                    }
                }
            }
            $imageAnnotator->close();

            if (!$isSafe) {
                Log::warning("Unsafe content: Post ID {$this->postId}");
                $post->delete();
                $this->sendFcmNotification($memberId, "Post Removed ⚠️", "Community standards violation.");
                return;
            }

            // ২. কম্প্রেশন ও থাম্বনেইল
            $fileNameBase = pathinfo($this->videoPath, PATHINFO_FILENAME);
            $compressedName = $fileNameBase . '_processed.mp4';
            $compressedPath = storage_path('app/temp_videos/' . $compressedName);
            $thumbName = $fileNameBase . '_thumb.jpg';
            $thumbnailPath = storage_path('app/temp_videos/' . $thumbName);

            // থাম্বনেইল
            if ($this->customThumbPath && file_exists($this->customThumbPath)) {
                copy($this->customThumbPath, $thumbnailPath);
            } else {
                $ffmpeg->getFrameFromSeconds(min(2, $duration))
                       ->export()
                       ->toDisk('local')
                       ->save('temp_videos/' . $thumbName);
            }
            $tempFiles[] = $thumbnailPath;

            // ভিডিও কম্প্রেশন (১০০০ বিটরেট)
            $format = (new X264('aac', 'libx264'))->setKiloBitrate(1000);
            $ffmpeg->export()
                ->toDisk('local')
                ->inFormat($format)
                ->addFilter('-preset', 'ultrafast')
                ->addFilter('-threads', 4)
                ->save('temp_videos/' . $compressedName);
            
            $tempFiles[] = $compressedPath;

            // ৩. GCS আপলোড
            $storage = new StorageClient(['projectId' => config('filesystems.disks.gcs.project_id'), 'keyFile' => $keyFileData]);
            $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

            $bucket->upload(fopen($compressedPath, 'r'), ['name' => "posts/videos/" . $compressedName, 'resumable' => true]);
            $bucket->upload(fopen($thumbnailPath, 'r'), ['name' => "posts/thumbnails/" . $thumbName]);

            // ৪. ডাটাবেজ আপডেট
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
            Log::error("Video Processing Failed (Post ID {$this->postId}): " . $e->getMessage());
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