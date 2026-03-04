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
        // ফাইলটি আসলে ভিডিও কি না এবং এক্সিস্ট করে কি না চেক
        if (!$post || !file_exists($this->videoPath)) {
            $this->cleanup([]);
            return;
        }

        $memberId = $post->member_id;
        $tempFiles = [];

        try {
            $keyFileData = config('filesystems.disks.gcs.key_file');
            $videoBaseName = basename($this->videoPath);
            
            // ভিডিওটি একবার ওপেন করুন
            $ffmpeg = FFMpeg::fromDisk('local')->open('temp_videos/' . $videoBaseName);
            $duration = $ffmpeg->getDurationInSeconds();

            // ১. স্মার্ট ফ্রেম সিলেকশন (৫ মিনিট=৩টি, ১০+ মিনিট=৬টি)
            $frameCount = ($duration > 600) ? 6 : 3;
            $isSafe = true;

            $imageAnnotator = new ImageAnnotatorClient(['credentials' => $keyFileData]);

            for ($i = 1; $i <= $frameCount; $i++) {
                $time = ($duration / ($frameCount + 1)) * $i;
                $frameName = "frame_{$this->postId}_{$i}.jpg";
                $framePath = storage_path('app/temp_videos/' . $frameName);
                
                // ফ্রেম এক্সপোর্ট (এখানে মূল ভিডিও অবজেক্ট $ffmpeg ব্যবহার হচ্ছে)
                $ffmpeg->getFrameFromSeconds($time)
                       ->export()
                       ->toDisk('local')
                       ->save('temp_videos/' . $frameName);
                
                $tempFiles[] = $framePath;

                if (file_exists($framePath)) {
                    $content = file_get_contents($framePath);
                    $response = $imageAnnotator->safeSearchDetection($content);
                    $safe = $response->getSafeSearchAnnotation();

                    // Adult, Racy, Violence চেক (Likelihood 4 = Likely, 5 = Very Likely)
                    if ($safe->getAdult() >= 4 || $safe->getRacy() >= 4 || $safe->getViolence() >= 4) {
                        $isSafe = false;
                        break;
                    }
                }
            }
            $imageAnnotator->close();

            // যদি আনসেফ হয় তবে পোস্ট ডিলিট
            if (!$isSafe) {
                Log::warning("Unsafe content detected in Video Post ID: {$this->postId}");
                $post->delete();
                $this->sendFcmNotification($memberId, "Post Removed ⚠️", "Your video violates community standards.");
                return;
            }

            // ২. ভিডিও কম্প্রেশন ও থাম্বনেইল
            $fileNameBase = pathinfo($this->videoPath, PATHINFO_FILENAME);
            $compressedName = $fileNameBase . '_processed.mp4';
            $compressedPath = storage_path('app/temp_videos/' . $compressedName);
            $thumbName = $fileNameBase . '_thumb.jpg';
            $thumbnailPath = storage_path('app/temp_videos/' . $thumbName);

            // থাম্বনেইল জেনারেশন
            if ($this->customThumbPath && file_exists($this->customThumbPath)) {
                copy($this->customThumbPath, $thumbnailPath);
            } else {
                $ffmpeg->getFrameFromSeconds(min(2, $duration))
                       ->export()
                       ->toDisk('local')
                       ->save('temp_videos/' . $thumbName);
            }
            $tempFiles[] = $thumbnailPath;

            // কম্প্রেশন লজিক (১০০০ বিটরেট, আল্ট্রাফাস্ট প্রিসেট)
            $format = (new X264('aac', 'libx264'))->setKiloBitrate(1000);
            $ffmpeg->export()
                ->toDisk('local')
                ->inFormat($format)
                ->addFilter('-preset', 'ultrafast')
                ->addFilter('-threads', 4)
                ->save('temp_videos/' . $compressedName);
            
            $tempFiles[] = $compressedPath;

            // ৩. GCS-এ আপলোড
            $storage = new StorageClient(['projectId' => config('filesystems.disks.gcs.project_id'), 'keyFile' => $keyFileData]);
            $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

            $gcsVideoPath = "posts/videos/" . $compressedName;
            $gcsThumbPath = "posts/thumbnails/" . $thumbName;

            $bucket->upload(fopen($compressedPath, 'r'), ['name' => $gcsVideoPath, 'resumable' => true]);
            $bucket->upload(fopen($thumbnailPath, 'r'), ['name' => $gcsThumbPath]);

            // ৪. ডাটাবেজ আপডেট
            Post_media::updateOrCreate(
                ['post_id' => $this->postId],
                [
                    'media_type' => 'video',
                    'path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $gcsVideoPath,
                    'thumbnail_path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $gcsThumbPath,
                    'duration' => round($duration),
                ]
            );

            $post->update(['status' => 'active']);
            $this->sendFcmNotification($memberId, "Success! ✅", "Your video is now live.");

        } catch (\Exception $e) {
            Log::error("Video Processing Failed (Post ID {$this->postId}): " . $e->getMessage());
            throw $e; // রিস্টার্ট করার জন্য এক্সেপশন থ্রো করা হলো
        } finally {
            $this->cleanup($tempFiles);
        }
    }

    protected function cleanup($files = [])
    {
        // অরিজিনাল আপলোড করা ফাইল ডিলিট
        if (file_exists($this->videoPath)) @unlink($this->videoPath);
        
        // কাস্টম থাম্ব ডিলিট
        if ($this->customThumbPath && file_exists($this->customThumbPath)) @unlink($this->customThumbPath);
        
        // তৈরি করা সব টেম্পোরারি ফাইল (Frames, Compressed Video, Thumb) ডিলিট
        foreach ($files as $file) {
            if (file_exists($file)) @unlink($file);
        }
    }
}