<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Intervention\Image\Facades\Image;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use App\Models\Post;
use App\Models\Post_media;
use App\Traits\NotificationTrait;
use App\Services\ErrorLogService;
use Illuminate\Support\Facades\DB;

class ProcessImageUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NotificationTrait;

    protected $postId, $tempPath, $fileNameBase;

    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct($postId, $tempPath, $fileNameBase)
    {
        $this->postId       = $postId;
        $this->tempPath     = $tempPath;
        $this->fileNameBase = $fileNameBase;
    }

    public function handle()
    {
        $post = Post::find($this->postId);

        if (!$post) {
            if (file_exists($this->tempPath)) unlink($this->tempPath);
            \Log::info("Post not found, skipping job: " . $this->postId);
            return;
        }

        $memberId = $post->member_id;

        try {
            $keyFileData = config('filesystems.disks.gcs.key_file');

            // ১. Vision API safety check
            $imageAnnotator = new ImageAnnotatorClient(['credentials' => $keyFileData]);
            $content        = file_get_contents($this->tempPath);
            $response       = $imageAnnotator->safeSearchDetection($content);
            $safe           = $response->getSafeSearchAnnotation();
            $imageAnnotator->close();

            // ২. 18+ হলে — শুধু এই image skip, post delete নয়
            if ($safe->getAdult() >= 4 || $safe->getRacy() >= 4) {
                \Log::warning("Inappropriate image detected for Post ID: {$this->postId}");

                // Pending count কমাও
                DB::table('posts')
                    ->where('id', $this->postId)
                    ->where('pending_media_count', '>', 0)
                    ->decrement('pending_media_count');

                $post->refresh();

                // User-কে এই image reject notification
                try {
                    $this->sendFcmNotification(
                        $memberId,
                        "Image Removed ⚠️",
                        "One of your images was removed for violating community standards.",
                        ['post_id' => (string) $this->postId, 'reason' => 'community_guidelines'],
                        'post'
                    );
                } catch (\Exception $e) {
                    \Log::error("FCM Rejection Notification Failed (Image): " . $e->getMessage());
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

                if (file_exists($this->tempPath)) unlink($this->tempPath);
                return;
            }

            // ৩. Image resize
            $img = Image::make($this->tempPath)->resize(1200, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })->encode('webp', 85);

            // ৪. GCS upload
            $storage  = new StorageClient([
                'projectId' => config('filesystems.disks.gcs.project_id'),
                'keyFile'   => $keyFileData,
            ]);
            $bucket   = $storage->bucket(config('filesystems.disks.gcs.bucket'));
            $fileName = "posts/images/{$this->fileNameBase}.webp";

            $bucket->upload((string) $img, [
                'name'     => $fileName,
                'metadata' => ['contentType' => 'image/webp'],
            ]);

            // ৫. Media record
            Post_media::create([
                'post_id'    => $this->postId,
                'media_type' => 'image',
                'path'       => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $fileName,
            ]);

            // ৬. Pending count কমাও — atomic operation
            DB::table('posts')
                ->where('id', $this->postId)
                ->where('pending_media_count', '>', 0)
                ->decrement('pending_media_count');

            $post->refresh();

            // ৭. সব media শেষ হলে post active + একটাই notification
            if ($post->pending_media_count === 0) {
                $post->update(['status' => 'active']);
                $this->sendSuccessNotification($post);
            }

        } catch (\Exception $e) {
            \Log::error("Image Processing Error: " . $e->getMessage());
            throw $e;
        } finally {
            if (file_exists($this->tempPath)) {
                unlink($this->tempPath);
            }
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
                ['post_id' => (string) $post->id, 'status' => 'active'],
                'post',
                (string) $post->id
            );
        } catch (\Exception $e) {
            \Log::error("FCM Success Notification Failed (Image): " . $e->getMessage());
        }
    }

    public function failed(\Throwable $e): void
    {
        $error = ErrorLogService::log(
            type:      'job_failed',
            source:    'ProcessImageUpload',
            message:   $e->getMessage(),
            exception: $e,
            context:   [
                'post_id'   => $this->postId,
                'temp_path' => $this->tempPath,
            ],
            jobClass:  self::class,
            jobParams: [
                'postId'       => $this->postId,
                'tempPath'     => $this->tempPath,
                'fileNameBase' => $this->fileNameBase,
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

            // বাকি media থাকলে active, না থাকলে failed
            if ($post->pending_media_count === 0 && $post->media()->count() > 0) {
                $post->update(['status' => 'active']);
            } elseif ($post->pending_media_count === 0 && $post->media()->count() === 0) {
                $post->update(['status' => 'failed']);
            }

            try {
                $this->sendFcmNotification(
                    $post->member_id,
                    "Image processing failed ⚠️",
                    "We are looking into it. Please try again later.",
                    ['post_id' => (string) $this->postId],
                    'post'
                );
            } catch (\Exception $ex) {
                \Log::error("FCM Failed Notification Error: " . $ex->getMessage());
            }
        }
    }
}