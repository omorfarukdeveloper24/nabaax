<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Vision\V1\ImageAnnotatorClient; // আমরা এখন এটি ব্যবহার করছি
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
        // ১. শুরুতে একটু ওয়েট করা যাতে ফাইল ডিস্কে রাইট হওয়ার সময় পায়
        sleep(5);

        $post = Post::find($this->postId);
        if (!$post || !file_exists($this->videoPath)) {
            Log::error("File not found at: " . $this->videoPath);
            $this->cleanupFiles();
            return;
        }

        $videoBaseName = basename($this->videoPath);
        $fileNameBase = pathinfo($this->videoPath, PATHINFO_FILENAME);
        $compressedPath = storage_path('app/temp_videos/' . $fileNameBase . '_processed.mp4');
        $thumbnailPath = storage_path('app/temp_videos/' . $fileNameBase . '_thumb.jpg');
        
        $tempFrames = [];

        try {
            $keyFileData = config('filesystems.disks.gcs.key_file');

            // ২. ভিডিও ওপেন (আপনার আগের মেথড অনুযায়ী)
            $media = FFMpeg::fromDisk('local')->open('temp_videos/' . $videoBaseName);
            $durationSeconds = $media->getDurationInSeconds();

            // ৩. সেফটি চেক (ভিডিও থেকে ৩টি ফ্রেম কেটে চেক করা)
            $isSafe = true;
            $imageAnnotator = new ImageAnnotatorClient(['credentials' => $keyFileData]);

            for ($i = 1; $i <= 3; $i++) {
                $time = ($durationSeconds / 4) * $i;
                $frameName = "frame_{$this->postId}_{$i}.jpg";
                $frameLocalPath = storage_path('app/temp_videos/' . $frameName);
                
                $media->getFrameFromSeconds($time)
                      ->export()
                      ->toDisk('local')
                      ->save('temp_videos/' . $frameName);

                $tempFrames[] = $frameLocalPath;

                if (file_exists($frameLocalPath)) {
                    $content = file_get_contents($frameLocalPath);
                    $response = $imageAnnotator->safeSearchDetection($content);
                    $safe = $response->getSafeSearchAnnotation();

                    // Adult, Racy, বা Violence ৪ এর উপরে হলে unsafe
                    if ($safe && ($safe->getAdult() >= 4 || $safe->getRacy() >= 4 || $safe->getViolence() >= 4)) {
                        $isSafe = false;
                        break;
                    }
                }
            }
            $imageAnnotator->close();

            if (!$isSafe) {
                Log::warning("Unsafe video detected (Vision API). Post ID: {$this->postId}");
                $post->delete();
                $this->cleanupFiles($tempFrames);
                return;
            }

            // ৪. থাম্বনেইল এবং ভিডিও কম্প্রেশন (আপনার আগের কোড অনুযায়ী)
            $this->generateThumbnail($videoBaseName, $durationSeconds, $thumbnailPath);
            $this->compressVideo($videoBaseName, $compressedPath);

            // ৫. GCS আপলোড
            $storage = new StorageClient([
                'projectId' => config('filesystems.disks.gcs.project_id'),
                'keyFile'    => $keyFileData,
            ]);
            $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));
            
            $gcsVideoPath = "posts/videos/" . basename($compressedPath);
            $gcsThumbPath = "posts/thumbnails/" . basename($thumbnailPath);

            $bucket->upload(fopen($compressedPath, 'r'), ['name' => $gcsVideoPath, 'resumable' => true]);
            $bucket->upload(fopen($thumbnailPath, 'r'), ['name' => $gcsThumbPath]);

            // ৬. ডাটাবেজ আপডেট
            Post_media::updateOrCreate(
                ['post_id' => $post->id],
                [
                    'path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/{$gcsVideoPath}",
                    'media_type' => 'video',
                    'thumbnail_path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/{$gcsThumbPath}",
                    'duration' => round($durationSeconds),
                ]
            );

            $post->update(['status' => 'active']);
            $this->sendFcmNotification($post->member_id, "Your video is live! ✅", "Processed successfully.");

            // ৭. ক্লিনআপ
            $this->cleanupFiles(array_merge($tempFrames, [$compressedPath, $thumbnailPath]));

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
        $format = (new X264('aac', 'libx264'))->setKiloBitrate(1200); 
        FFMpeg::fromDisk('local')->open('temp_videos/' . $videoBaseName)
            ->export()->toDisk('local')->inFormat($format)
            ->addFilter('-preset', 'veryfast')
            ->addFilter('-threads', 2)
            ->save('temp_videos/' . basename($compressedPath));
    }

    protected function cleanupFiles($extraFiles = [])
    {
        if (file_exists($this->videoPath)) @unlink($this->videoPath);
        if ($this->customThumbPath && file_exists($this->customThumbPath)) @unlink($this->customThumbPath);
        foreach ($extraFiles as $file) {
            if (file_exists($file)) @unlink($file);
        }
    }
}