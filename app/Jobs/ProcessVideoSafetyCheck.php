<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use App\Models\Post;
use App\Models\Post_media;
use App\Traits\NotificationTrait;
use Illuminate\Support\Facades\Log;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Coordinate\TimeCode;

class ProcessVideoSafetyCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NotificationTrait;

    protected $postId, $videoPath, $customThumbPath;
    public $timeout = 1800; // ৩০ মিনিট
    public $tries = 1;

    public function __construct($postId, $videoPath, $customThumbPath = null)
    {
        $this->postId = $postId;
        $this->videoPath = $videoPath;
        $this->customThumbPath = $customThumbPath;
    }

    public function handle()
    {
        // ১. শুরুতে ১০ সেকেন্ড অপেক্ষা (ফাইল রাইট হওয়ার জন্য সময় দেয়া)
        sleep(10);

        $post = Post::find($this->postId);
        if (!$post || !file_exists($this->videoPath)) {
            Log::error("Post or File not found. Post ID: {$this->postId}, Path: {$this->videoPath}");
            return;
        }

        $fileNameBase = pathinfo($this->videoPath, PATHINFO_FILENAME);
        $tempDir = storage_path('app/temp_videos/');
        
        // ডিরেক্টরি না থাকলে তৈরি করে নেয়া
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $compressedPath = $tempDir . $fileNameBase . '_processed.mp4';
        $thumbnailPath = $tempDir . $fileNameBase . '_thumb.jpg';
        $tempFiles = [];

        try {
            $keyFileData = config('filesystems.disks.gcs.key_file');

            // ২. FFProbe দিয়ে ডিউরেশন চেক
            $ffprobe = FFProbe::create([
                'ffprobe.binaries' => '/usr/bin/ffprobe',
            ]);
            $durationSeconds = (float) $ffprobe->format($this->videoPath)->get('duration');

            // ৩. সেফটি চেক (Google Vision)
            $isSafe = $this->runSafetyCheck($keyFileData, $durationSeconds, $tempFiles);

            if (!$isSafe) {
                Log::warning("Unsafe video detected! Deleting Post ID: {$this->postId}");
                $post->delete();
                $this->cleanupFiles(array_merge($tempFiles, [$this->videoPath]));
                return;
            }

            // ৪. থাম্বনেইল জেনারেশন
            $this->generateThumbnail($durationSeconds, $thumbnailPath);

            // ৫. ভিডিও কম্প্রেশন (Shell Command)
            $this->compressVideo($compressedPath);

            // ৬. GCS আপলোড
            $storage = new StorageClient([
                'projectId' => config('filesystems.disks.gcs.project_id'),
                'keyFile' => $keyFileData
            ]);
            $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));
            
            $gcsVideoPath = "posts/videos/" . basename($compressedPath);
            $gcsThumbPath = "posts/thumbnails/" . basename($thumbnailPath);

            $bucket->upload(fopen($compressedPath, 'r'), ['name' => $gcsVideoPath]);
            $bucket->upload(fopen($thumbnailPath, 'r'), ['name' => $gcsThumbPath]);

            // ৭. ডাটাবেজ আপডেট
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
            
            // নোটিফিকেশন পাঠানো
            $this->sendFcmNotification($post->member_id, "Your video is live! ✅", "Processed successfully.");

            // ফাইনাল ক্লিনআপ
            $this->cleanupFiles(array_merge($tempFiles, [$compressedPath, $thumbnailPath, $this->videoPath]));

        } catch (\Exception $e) {
            Log::error("Critical Video Error (Post ID {$this->postId}): " . $e->getMessage());
            $this->cleanupFiles(array_merge($tempFiles, [$compressedPath ?? null, $thumbnailPath ?? null]));
            throw $e;
        }
    }

    protected function runSafetyCheck($keyFileData, $durationSeconds, &$tempFiles)
    {
        $ffmpeg = FFMpeg::create(['ffmpeg.binaries' => '/usr/bin/ffmpeg', 'ffprobe.binaries' => '/usr/bin/ffprobe']);
        $video = $ffmpeg->open($this->videoPath);
        $imageAnnotator = new ImageAnnotatorClient(['credentials' => $keyFileData]);
        $isSafe = true;

        for ($i = 1; $i <= 3; $i++) {
            $time = ($durationSeconds / 4) * $i;
            $framePath = storage_path("app/temp_videos/frame_{$this->postId}_{$i}.jpg");
            
            $video->frame(TimeCode::fromSeconds($time))->save($framePath);
            $tempFiles[] = $framePath;

            if (file_exists($framePath)) {
                $response = $imageAnnotator->safeSearchDetection(file_get_contents($framePath));
                $safe = $response->getSafeSearchAnnotation();

                if ($safe && ($safe->getAdult() >= 4 || $safe->getRacy() >= 4)) {
                    $isSafe = false;
                    break;
                }
            }
        }
        $imageAnnotator->close();
        return $isSafe;
    }

    protected function generateThumbnail($durationSeconds, $thumbnailPath) 
    {
        if ($this->customThumbPath && file_exists($this->customThumbPath)) {
            copy($this->customThumbPath, $thumbnailPath);
        } else {
            $ffmpeg = FFMpeg::create(['ffmpeg.binaries' => '/usr/bin/ffmpeg', 'ffprobe.binaries' => '/usr/bin/ffprobe']);
            $video = $ffmpeg->open($this->videoPath);
            $video->frame(TimeCode::fromSeconds(min(1, $durationSeconds)))->save($thumbnailPath);
        }
    }

    protected function compressVideo($compressedPath) 
    {
        $command = "/usr/bin/ffmpeg -y -i " . escapeshellarg($this->videoPath) . " -c:v libx264 -preset veryfast -b:v 1200k -c:a aac -threads 2 " . escapeshellarg($compressedPath) . " 2>&1";
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new \Exception("FFMpeg compression failed: " . implode("\n", $output));
        }
    }

    protected function cleanupFiles(array $files)
    {
        foreach ($files as $file) {
            if ($file && file_exists($file)) {
                @unlink($file);
            }
        }
    }
}