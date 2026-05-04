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

        // ❌ আগের check সরিয়ে দাও — এটাই সমস্যা ছিল
        // status check এখানে করা যাবে না কারণ parallel jobs একে অপরকে block করে

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

            } else {
                $img = Image::make($this->tempPath)->resize(1200, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })->encode('webp', 85);

                $storage = new StorageClient([
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
            }

        } catch (\Exception $e) {
            \Log::error("Image Processing Error: " . $e->getMessage());
            throw $e;
        } finally {
            if (file_exists($this->tempPath)) unlink($this->tempPath);
        }

        $this->finalizeMediaProcessing();
    }

    protected function finalizeMediaProcessing()
    {
        // Atomic decrement
        Post::where('id', $this->postId)
            ->update(['pending_media_count' => DB::raw('GREATEST(pending_media_count - 1, 0)')]);

        // ✅ শুধু একটাই Job এই update করতে পারবে
        // 'pending' থেকে 'processing_done' — MySQL level-এ atomic
        $updated = Post::where('id', $this->postId)
            ->where('pending_media_count', 0)
            ->where('status', 'pending')
            ->update(['status' => 'processing_done']);

        if ($updated === 1) {
            $this->sendFinalNotification();
        }
    }

    protected function sendFinalNotification()
    {
        $post = Post::find($this->postId);
        if (!$post) return;

        $approvedCount = Post_media::where('post_id', $this->postId)->count();
        $rejectedCount = $post->rejected_media_count ?? 0;

        if ($approvedCount > 0) {
            // ১. অন্তত একটি ইমেজ অ্যাপ্রুভ হয়েছে — Status: Active
            $post->update(['status' => 'active']);

            if ($rejectedCount > 0) {
                // কিছু অ্যাপ্রুভড, কিছু রিজেক্টেড
                $this->sendFcmNotification(
                    $post->member_id,
                    "1..Your post is live ✅",
                    "{$approvedCount} image(s) published. {$rejectedCount} image(s) violated our community standards.",
                    ['post_id' => (string) $post->id, 'status' => 'active'],
                    'post',
                    (string) $post->id
                );
            } else {
                // সব অ্যাপ্রুভড
                $this->sendFcmNotification(
                    $post->member_id,
                    "2..Your post is live ✅",
                    "All {$approvedCount} image(s) published successfully.",
                    ['post_id' => (string) $post->id, 'status' => 'active'],
                    'post',
                    (string) $post->id
                );
            }

        } else {
            // ২. কোনো অ্যাপ্রুভড ইমেজ নেই (approvedCount == 0)
            
            // চেক করছি পোস্ট মিডিয়াতে কোনো ডাটা আছে কি না
            $mediaExists = Post_media::where('post_id', $this->postId)->exists();

            if ($mediaExists) {
                // মিডিয়া আছে কিন্তু অ্যাপ্রুভড কাউন্ট ০ — Status: Active (আপনার রিকোয়ারমেন্ট অনুযায়ী)
                $post->update(['status' => 'active']);

                $this->sendFcmNotification(
                    $post->member_id,
                    "3..Post removed ⚠️",
                    "Total {$rejectedCount} image(s) violated our community standards.",
                    ['post_id' => (string) $post->id, 'status' => 'active'],
                    'post',
                    (string) $post->id
                );
            } else {
                // কোনো মিডিয়া নেই — Status: Failed
                $post->update(['status' => 'failed']);

                $this->sendFcmNotification(
                    $post->member_id,
                    "4..Post removed ⚠️",
                    "All {$rejectedCount} image(s) violated our community standards.",
                    ['post_id' => (string) $post->id, 'status' => 'failed'],
                    'post',
                    (string) $post->id
                );
            }
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

        // Failed job-এর জন্যও count কমাও
        $this->finalizeMediaProcessing();
    }
}