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
            $this->cleanup();
            return;
        }

        $memberId = $post->member_id;
        $tempFiles = [];

        try {
            $keyFileData = config('filesystems.disks.gcs.key_file');
            $ffmpeg = FFMpeg::fromDisk('local')->open('temp_videos/' . basename($this->videoPath));
            $duration = $ffmpeg->getDurationInSeconds();

            // ১. স্মার্ট ফ্রেম সিলেকশন (টিকটক লজিক)
            $frameCount = ($duration > 600) ? 6 : 3;
            $isSafe = true;

            $imageAnnotator = new ImageAnnotatorClient(['credentials' => $keyFileData]);

            for ($i = 1; $i <= $frameCount; $i++) {
                $time = ($duration / ($frameCount + 1)) * $i;
                $frameName = "frame_{$this->postId}_{$i}.jpg";
                $framePath = storage_path('app/temp_videos/' . $frameName);
                
                $ffmpeg->getFrameFromSeconds($time)->export()->toDisk('local')->save('temp_videos/' . $frameName);
                $tempFiles[] = $framePath;

                $content = file_get_contents($framePath);
                $response = $imageAnnotator->safeSearchDetection($content);
                $safe = $response->getSafeSearchAnnotation();

                // Adult, Racy বা Violence চেক
                if ($safe->getAdult() >= 4 || $safe->getRacy() >= 4 || $safe->getViolence() >= 4) {
                    $isSafe = false;
                    break;
                }
            }
            $imageAnnotator->close();

            if (!$isSafe) {
                $post->delete();
                $this->sendFcmNotification($memberId, "Post Removed ⚠️", "Your video violates community standards.");
                return;
            }

            // ২. ভিডিও কম্প্রেশন (১০০০ বিটরেট এবং আল্ট্রাফাস্ট প্রসেসিং)
            $fileNameBase = pathinfo($this->videoPath, PATHINFO_FILENAME);
            $compressedPath = storage_path('app/temp_videos/' . $fileNameBase . '_processed.mp4');
            $thumbnailPath = storage_path('app/temp_videos/' . $fileNameBase . '_thumb.jpg');

            // থাম্বনেইল
            if ($this->customThumbPath && file_exists($this->customThumbPath)) {
                copy($this->customThumbPath, $thumbnailPath);
            } else {
                $ffmpeg->getFrameFromSeconds(min(2, $duration))->export()->toDisk('local')->save('temp_videos/' . basename($thumbnailPath));
            }
            $tempFiles[] = $thumbnailPath;

            // কম্প্রেশন লজিক
            $format = (new X264('aac', 'libx264'))->setKiloBitrate(1000); // এখানে ১০০০ বিটরেট সেট করা হয়েছে
            $ffmpeg->export()
                ->toDisk('local')
                ->inFormat($format)
                ->addFilter('-preset', 'ultrafast') // ২০ সেকেন্ডে শেষ করার জন্য
                ->addFilter('-threads', 4)         // সার্ভারের শক্তি ব্যবহারের জন্য
                ->save('temp_videos/' . basename($compressedPath));
            
            $tempFiles[] = $compressedPath;

            // ৩. GCS-এ আপলোড
            $storage = new StorageClient(['projectId' => config('filesystems.disks.gcs.project_id'), 'keyFile' => $keyFileData]);
            $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

            $gcsVideoName = "posts/videos/" . basename($compressedPath);
            $gcsThumbName = "posts/thumbnails/" . basename($thumbnailPath);

            $bucket->upload(fopen($compressedPath, 'r'), ['name' => $gcsVideoName, 'resumable' => true]);
            $bucket->upload(fopen($thumbnailPath, 'r'), ['name' => $gcsThumbName]);

            // ৪. রেকর্ড আপডেট
            Post_media::create([
                'post_id' => $post->id,
                'media_type' => 'video',
                'path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $gcsVideoName,
                'thumbnail_path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $gcsThumbName,
                'duration' => round($duration),
            ]);

            $post->update(['status' => 'active']);
            $this->sendFcmNotification($memberId, "Success! ✅", "Your video is now live.");

        } catch (\Exception $e) {
            Log::error("Video Processing Failed: " . $e->getMessage());
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