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
use App\Services\ErrorLogService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Coordinate\TimeCode;

class ProcessVideoSafetyCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NotificationTrait;

    protected $postId, $videoPath, $customThumbPath;
    public $timeout = 1800;
    public $tries   = 3;

    public function __construct($postId, $videoPath, $customThumbPath = null)
    {
        $this->postId          = $postId;
        $this->videoPath       = $videoPath;
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

        $fileNameBase   = pathinfo($this->videoPath, PATHINFO_FILENAME);
        $tempDir        = storage_path('app/temp_videos/');
        if (!file_exists($tempDir)) mkdir($tempDir, 0777, true);

        $compressedPath = $tempDir . $fileNameBase . '_processed.mp4';
        $thumbnailPath  = $tempDir . $fileNameBase . '_thumb.jpg';
        $tempFiles      = [];

        try {
            $keyFileData = config('filesystems.disks.gcs.key_file');
            $storage = new StorageClient([
                'projectId' => config('filesystems.disks.gcs.project_id'),
                'keyFile'   => $keyFileData,
            ]);
            $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

            // ১. Duration
            $ffprobe         = FFProbe::create(['ffprobe.binaries' => '/usr/bin/ffprobe']);
            $durationSeconds = (float) $ffprobe->format($this->videoPath)->get('duration');

            // ২. Safety check
            $isSafe = $this->runSafetyCheck($keyFileData, $durationSeconds, $tempFiles);

            // ৩. 18+ হলে — শুধু এই video skip, post delete নয়
            if (!$isSafe) {
                Log::warning("Unsafe content detected in Post ID: {$this->postId}");

                // Pending count কমাও
                DB::table('posts')
                    ->where('id', $this->postId)
                    ->where('pending_media_count', '>', 0)
                    ->decrement('pending_media_count');

                $post->refresh();

                // Video reject notification
                try {
                    $this->sendFcmNotification(
                        $post->member_id,
                        "Video Rejected ❌",
                        "One of your videos was removed for violating community standards.",
                        ['post_id' => (string) $this->postId, 'reason' => 'unsafe_content'],
                        'post'
                    );
                } catch (\Exception $e) {
                    Log::error("FCM Rejection Notification Failed: " . $e->getMessage());
                }

                // বাকি media থাকলে post active, না থাকলে delete
                if ($post->pending_media_count === 0) {
                    if ($post->media()->count() > 0) {
                        $post->update(['status' => 'active']);
                        $this->sendSuccessNotification($post);
                    } else {
                        $post->delete();
                    }
                }

                $this->cleanupFiles(array_merge($tempFiles, [$this->videoPath]));
                return;
            }

            // ৪. Thumbnail + Compression
            $this->generateThumbnail($durationSeconds, $thumbnailPath);
            $this->compressVideo($compressedPath);

            // ৫. GCS Upload
            $gcsVideoPath = "posts/videos/" . basename($compressedPath);
            $gcsThumbPath = "posts/thumbnails/" . basename($thumbnailPath);

            $bucket->upload(fopen($compressedPath, 'r'), ['name' => $gcsVideoPath]);
            $bucket->upload(fopen($thumbnailPath, 'r'),  ['name' => $gcsThumbPath]);

            // ৬. DB — create করো, updateOrCreate নয়
            Post_media::create([
                'post_id'        => $post->id,
                'path'           => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/{$gcsVideoPath}",
                'media_type'     => 'video',
                'thumbnail_path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/{$gcsThumbPath}",
                'duration'       => round($durationSeconds),
            ]);

            // ৭. Pending count কমাও — atomic
            DB::table('posts')
                ->where('id', $this->postId)
                ->where('pending_media_count', '>', 0)
                ->decrement('pending_media_count');

            $post->refresh();

            // ৮. সব media শেষ হলে post active + একটাই notification
            if ($post->pending_media_count === 0) {
                $post->update(['status' => 'active']);
                $this->sendSuccessNotification($post);
            }

            // ৯. Cleanup
            $this->cleanupFiles(array_merge($tempFiles, [$compressedPath, $thumbnailPath, $this->videoPath]));

        } catch (\Exception $e) {
            Log::error("Critical Video Error (Post ID {$this->postId}): " . $e->getMessage());
            $this->cleanupFiles(array_merge($tempFiles, [$compressedPath ?? null, $thumbnailPath ?? null]));
            throw $e;
        }
    }

    // সব media ready হলে একটাই notification
    private function sendSuccessNotification(Post $post): void
    {
        try {
            $this->sendFcmNotification(
                $post->member_id,
                "Your post is live! ✅",
                "Your post has been processed and is now live.",
                ['post_id' => (string) $post->id, 'status' => 'active', 'type' => 'post'],
                'post',
                (string) $post->id
            );
        } catch (\Exception $e) {
            Log::error("FCM Success Notification Failed (Video): " . $e->getMessage());
        }
    }

    public function failed(\Throwable $e): void
    {
        $error = ErrorLogService::log(
            type:      'job_failed',
            source:    'ProcessVideoSafetyCheck',
            message:   $e->getMessage(),
            exception: $e,
            context:   [
                'post_id'    => $this->postId,
                'video_path' => $this->videoPath,
            ],
            jobClass:  self::class,
            jobParams: [
                'postId'          => $this->postId,
                'videoPath'       => $this->videoPath,
                'customThumbPath' => $this->customThumbPath,
            ],
            maxRetries: $this->tries
        );

        ErrorLogService::jobFailed($error, $e);

        // Pending count কমাও
        DB::table('posts')
            ->where('id', $this->postId)
            ->where('pending_media_count', '>', 0)
            ->decrement('pending_media_count');

        $post = Post::find($this->postId);
        if ($post) {
            $post->refresh();

            if ($post->pending_media_count === 0 && $post->media()->count() > 0) {
                $post->update(['status' => 'active']);
            } elseif ($post->pending_media_count === 0 && $post->media()->count() === 0) {
                $post->update(['status' => 'failed']);
            }

            try {
                $this->sendFcmNotification(
                    $post->member_id,
                    "Video processing failed ⚠️",
                    "We are looking into it. Please try again later.",
                    ['post_id' => (string) $this->postId],
                    'post'
                );
            } catch (\Exception $ex) {
                Log::error("FCM Failed Notification Error: " . $ex->getMessage());
            }
        }
    }

    protected function runSafetyCheck($keyFileData, $durationSeconds, &$tempFiles)
    {
        $ffmpeg = FFMpeg::create([
            'ffmpeg.binaries'  => '/usr/bin/ffmpeg',
            'ffprobe.binaries' => '/usr/bin/ffprobe',
        ]);
        $video          = $ffmpeg->open($this->videoPath);
        $imageAnnotator = new ImageAnnotatorClient(['credentials' => $keyFileData]);
        $isSafe         = true;

        for ($i = 1; $i <= 3; $i++) {
            $time      = ($durationSeconds / 4) * $i;
            $framePath = storage_path("app/temp_videos/frame_{$this->postId}_{$i}.jpg");
            $video->frame(TimeCode::fromSeconds($time))->save($framePath);
            $tempFiles[] = $framePath;

            if (file_exists($framePath)) {
                $response = $imageAnnotator->safeSearchDetection(file_get_contents($framePath));
                $safe     = $response->getSafeSearchAnnotation();
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
            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries'  => '/usr/bin/ffmpeg',
                'ffprobe.binaries' => '/usr/bin/ffprobe',
            ]);
            $video = $ffmpeg->open($this->videoPath);
            $video->frame(TimeCode::fromSeconds(min(1, $durationSeconds)))->save($thumbnailPath);
        }
    }

    protected function compressVideo($compressedPath)
    {
        $command = "/usr/bin/ffmpeg -y -i " . escapeshellarg($this->videoPath) .
            " -vcodec libx264 -crf 28 -preset veryfast" .
            " -vf \"scale='min(720,iw)':-2\"" .
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