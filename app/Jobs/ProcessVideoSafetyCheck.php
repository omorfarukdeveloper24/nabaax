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
        sleep(10);

        $post = Post::find($this->postId);
        if (!$post || !file_exists($this->videoPath)) {
            Log::error("Post or File not found. Post ID: {$this->postId}");
            return;
        }

        $fileNameBase = pathinfo($this->videoPath, PATHINFO_FILENAME);
        $tempDir = storage_path('app/temp_videos/');
        if (!file_exists($tempDir)) mkdir($tempDir, 0777, true);

        $compressedPath = $tempDir . $fileNameBase . '_processed.mp4';
        $thumbnailPath = $tempDir . $fileNameBase . '_thumb.jpg';
        $tempFiles = [];

        try {
            $keyFileData = config('filesystems.disks.gcs.key_file');
            $storage = new StorageClient([
                'projectId' => config('filesystems.disks.gcs.project_id'),
                'keyFile' => $keyFileData
            ]);
            $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

            // ১. ডিউরেশন বের করা
            $ffprobe = FFProbe::create(['ffprobe.binaries' => '/usr/bin/ffprobe']);
            $durationSeconds = (float) $ffprobe->format($this->videoPath)->get('duration');

            // ২. সেফটি চেক (Google Vision)
            $isSafe = $this->runSafetyCheck($keyFileData, $durationSeconds, $tempFiles);

            if (!$isSafe) {
                Log::warning("Unsafe content (18+) detected in Post ID: {$this->postId}");
                
                // ইউজারকে রিজেকশন নোটিফিকেশন পাঠানো
                $this->sendFcmNotification($post->member_id, "Video Rejected! ❌", "Your video contains restricted content and was removed.");

                // ডাটাবেজ থেকে পোস্ট ডিলিট
                $post->delete();

                // লোকাল ফাইল এবং যদি GCS-এ ফাইল থাকে তবে তা ডিলিট করা
                $this->cleanupFiles(array_merge($tempFiles, [$this->videoPath]));
                return;
            }

            // ৩. থাম্বনেইল এবং ভিডিও কম্প্রেশন
            $this->generateThumbnail($durationSeconds, $thumbnailPath);
            $this->compressVideo($compressedPath);

            // ৪. GCS আপলোড
            $gcsVideoPath = "posts/videos/" . basename($compressedPath);
            $gcsThumbPath = "posts/thumbnails/" . basename($thumbnailPath);

            $bucket->upload(fopen($compressedPath, 'r'), ['name' => $gcsVideoPath]);
            $bucket->upload(fopen($thumbnailPath, 'r'), ['name' => $gcsThumbPath]);

            // ৫. ডাটাবেজ আপডেট
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

            // ৬. ফাইনাল ক্লিনআপ
            $this->cleanupFiles(array_merge($tempFiles, [$compressedPath, $thumbnailPath, $this->videoPath]));

        } catch (\Exception $e) {
            Log::error("Critical Video Error (Post ID {$this->postId}): " . $e->getMessage());
            $this->cleanupFiles(array_merge($tempFiles, [$compressedPath ?? null, $thumbnailPath ?? null]));
            throw $e;
        }
    }

    // Safety Check logic remains same as per your requirement
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
                
                // Adult or Racy content check (4 = Likely, 5 = Very Likely)
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
        
        $command = "/usr/bin/ffmpeg -y -i " . escapeshellarg($this->videoPath) . 
                " -vcodec libx264 -crf 28 -preset veryfast " . 
                " -vf \"scale='min(720,iw)':-2\" " . 
                " -acodec aac -b:a 128k -movflags +faststart -threads 2 " . 
                escapeshellarg($compressedPath) . " 2>&1";

        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new \Exception("FFMpeg compression failed: " . implode("\n", $output));
        }
    }

    protected function cleanupFiles(array $files)
    {
        foreach ($files as $file) {
            if ($file && file_exists($file)) @unlink($file);
        }
    }
}