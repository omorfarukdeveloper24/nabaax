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

    // public function handle()
    // {
    //     $post = Post::find($this->postId);

    //     if (!$post) {
    //         if (file_exists($this->tempPath)) unlink($this->tempPath);
    //         return;
    //     }

    //     $uploadSuccess = false;

    //     try {
    //         $keyFileData = config('filesystems.disks.gcs.key_file');

    //         // ১. Vision API Safety Check
    //         $imageAnnotator = new ImageAnnotatorClient(['credentials' => $keyFileData]);
    //         $content        = file_get_contents($this->tempPath);
    //         $response       = $imageAnnotator->safeSearchDetection($content);
    //         $safe           = $response->getSafeSearchAnnotation();
    //         $imageAnnotator->close();

    //         // ২. 18+ ধরা পড়লে — শুধু এই image skip, post delete নয়
    //         if ($safe->getAdult() >= 4 || $safe->getRacy() >= 4) {
    //             \Log::warning("18+ image skipped. Post ID: {$this->postId}, File: {$this->fileNameBase}");

    //             // rejected_media_count বাড়াও — atomic
    //             // ৩. DB::raw দিয়ে atomic increment — race condition নেই
    //             Post::where('id', $this->postId)
    //                 ->update(['rejected_media_count' => DB::raw('rejected_media_count + 1')]);

    //             $uploadSuccess = false; // এই image upload হয়নি

    //         } else {
    //             // ৩. Image Processing
    //             $img = Image::make($this->tempPath)->resize(1200, null, function ($constraint) {
    //                 $constraint->aspectRatio();
    //                 $constraint->upsize();
    //             })->encode('webp', 85);

    //             // ৪. GCS Upload
    //             $storage  = new StorageClient([
    //                 'projectId' => config('filesystems.disks.gcs.project_id'),
    //                 'keyFile'   => $keyFileData,
    //             ]);
    //             $bucket   = $storage->bucket(config('filesystems.disks.gcs.bucket'));
    //             $fileName = "posts/images/{$this->fileNameBase}.webp";

    //             $bucket->upload((string) $img, [
    //                 'name'     => $fileName,
    //                 'metadata' => ['contentType' => 'image/webp'],
    //             ]);

    //             Post_media::create([
    //                 'post_id'    => $this->postId,
    //                 'media_type' => 'image',
    //                 'path'       => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $fileName,
    //             ]);

    //             $uploadSuccess = true;
    //         }

    //     } catch (\Exception $e) {
    //         \Log::error("Image Processing Error: " . $e->getMessage());
    //         $uploadSuccess = false;
    //         throw $e; // retry হবে
    //     } finally {
    //         if (file_exists($this->tempPath)) unlink($this->tempPath);
    //     }

    //     // ৫. সব শেষে pending count কমাও এবং চেক করো
    //     $this->finalizeMediaProcessing($post);
    // }


    public function handle()
    {
        $post = Post::find($this->postId);

        if (!$post) {
            if (file_exists($this->tempPath)) unlink($this->tempPath);
            return;
        }

        // ✅ নতুন — already processed কিনা check
        // যদি status pending না হয়, মানে সব শেষ হয়ে গেছে
        if (!in_array($post->status, ['pending'])) {
            if (file_exists($this->tempPath)) unlink($this->tempPath);
            return;
        }

        $uploadSuccess = false;

        try {
            $keyFileData = config('filesystems.disks.gcs.key_file');

            $imageAnnotator = new ImageAnnotatorClient(['credentials' => $keyFileData]);
            $content        = file_get_contents($this->tempPath);
            $response       = $imageAnnotator->safeSearchDetection($content);
            $safe           = $response->getSafeSearchAnnotation();
            $imageAnnotator->close();

            if ($safe->getAdult() >= 4 || $safe->getRacy() >= 4) {
                \Log::warning("18+ image skipped. Post ID: {$this->postId}, File: {$this->fileNameBase}");

                Post::where('id', $this->postId)
                    ->update(['rejected_media_count' => DB::raw('rejected_media_count + 1')]);

                $uploadSuccess = false;

            } else {
                $img = Image::make($this->tempPath)->resize(1200, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })->encode('webp', 85);

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

                Post_media::create([
                    'post_id'    => $this->postId,
                    'media_type' => 'image',
                    'path'       => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $fileName,
                ]);

                $uploadSuccess = true;
            }

        } catch (\Exception $e) {
            \Log::error("Image Processing Error: " . $e->getMessage());
            $uploadSuccess = false;
            throw $e;
        } finally {
            if (file_exists($this->tempPath)) unlink($this->tempPath);
        }

        $this->finalizeMediaProcessing($post);
    }

    protected function finalizeMediaProcessing($post)
    {
        // ৬. Atomic decrement — 10টা job parallel চললেও safe
        Post::where('id', $this->postId)
            ->update(['pending_media_count' => DB::raw('GREATEST(pending_media_count - 1, 0)')]);

        // ৭. এখন check করো — সব job শেষ হয়েছে কিনা
        // updateOrFail দিয়ে শুধু একটাই job "finalize" করবে — race condition বন্ধ
        $updated = Post::where('id', $this->postId)
            ->where('pending_media_count', 0)  // শুধু তখনই
            ->where('status', 'pending')        // একবারই চলবে
            ->update(['status' => 'processing_done']); // temp status

        // ৮. শুধু একটাই job এই block-এ ঢুকবে (যে update করতে পেরেছে)
        if ($updated === 1) {
            $this->sendFinalNotification($post);
        }
    }

    protected function sendFinalNotification($post)
    {
        // ৯. Fresh data নাও
        $post = Post::find($this->postId);
        if (!$post) return;

        $approvedCount = Post_media::where('post_id', $this->postId)->count();
        $rejectedCount = $post->rejected_media_count ?? 0;

        if ($approvedCount > 0) {
            // ১০. Post active করো
            $post->update(['status' => 'active']);

            // ১১. একটাই notification — সব info একসাথে
            if ($rejectedCount > 0) {
                // কিছু approved, কিছু rejected
                $this->sendFcmNotification(
                    $post->member_id,
                    "Your post is live ✅",
                    "{$approvedCount} image(s) published. {$rejectedCount} image(s) removed for violating community standards.",
                    [
                        'post_id'        => (string) $post->id,
                        'status'         => 'active',
                        'approved_count' => (string) $approvedCount,
                        'rejected_count' => (string) $rejectedCount,
                    ],
                    'post',
                    (string) $post->id
                );
            } else {
                // সব approved
                $this->sendFcmNotification(
                    $post->member_id,
                    "Your post is live ✅",
                    "All {$approvedCount} image(s) published successfully.",
                    [
                        'post_id' => (string) $post->id,
                        'status'  => 'active',
                    ],
                    'post',
                    (string) $post->id
                );
            }

        } else {
            // ১২. সব rejected — post failed
            $post->update(['status' => 'failed']);

            $this->sendFcmNotification(
                $post->member_id,
                "Post removed ⚠️",
                "All {$rejectedCount} image(s) violated our community standards. Your post has been removed.",
                [
                    'post_id'        => (string) $post->id,
                    'status'         => 'failed',
                    'rejected_count' => (string) $rejectedCount,
                ],
                'post'
            );
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
                'post_id'        => $this->postId,
                'temp_path'      => $this->tempPath,
                'file_name_base' => $this->fileNameBase,
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

        // ১৩. Failed job-এর জন্যও pending count কমাও
        // নাহলে post সারাজীবন pending থাকবে
        $this->finalizeMediaProcessing(Post::find($this->postId) ?? new Post(['id' => $this->postId]));
    }
}