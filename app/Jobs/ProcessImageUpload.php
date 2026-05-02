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

class ProcessImageUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NotificationTrait;

    protected $postId, $tempPath, $fileNameBase;

    public int $tries   = 3;  // ৩ বার retry
    public int $backoff = 30; // ৩০ সেকেন্ড পর retry

    public function __construct($postId, $tempPath, $fileNameBase)
    {
        $this->postId       = $postId;
        $this->tempPath     = $tempPath;
        $this->fileNameBase = $fileNameBase;
    }

    public function handle()
    {
        // ১. lockForUpdate() এখানে সরাসরি না ব্যবহার করে কাউন্ট কমানোর সময় ব্যবহার করা ভালো।
        $post = Post::find($this->postId);

        if (!$post) {
            if (file_exists($this->tempPath)) unlink($this->tempPath);
            return;
        }

        try {
            $keyFileData = config('filesystems.disks.gcs.key_file');
            
            // ২. Vision API Safety Check
            $imageAnnotator = new ImageAnnotatorClient(['credentials' => $keyFileData]);
            $content = file_get_contents($this->tempPath);
            $response = $imageAnnotator->safeSearchDetection($content);
            $safe = $response->getSafeSearchAnnotation();
            $imageAnnotator->close();

            // ১৮+ ইমেজ ডিটেকশন
            if ($safe->getAdult() >= 4 || $safe->getRacy() >= 4) {
                \Log::warning("Inappropriate image skipped for Post ID: {$this->postId}");
                
                // ৩. এখানে একটি কাস্টম কলাম বা ফ্ল্যাগ ব্যবহার করলে ভালো হয় ইউজারকে জানানোর জন্য যে "কিছু ইমেজ বাদ গেছে"
                // আপাতত আপনার রিকোয়ারমেন্ট অনুযায়ী শুধু কাউন্ট কমিয়ে প্রসেস শেষ করছি।
                $this->finalizeMediaProcessing($post, false); 
                return;
            }

            // ৪. ইমেজ প্রসেসিং এবং আপলোড
            $img = Image::make($this->tempPath)->resize(1200, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })->encode('webp', 85);

            $storage = new StorageClient([
                'projectId' => config('filesystems.disks.gcs.project_id'),
                'keyFile'   => $keyFileData,
            ]);
            $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));
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

            $this->finalizeMediaProcessing($post, true);

        } catch (\Exception $e) {
            \Log::error("Image Processing Error: " . $e->getMessage());
            // ৫. জবে এরর আসলেও কাউন্ট কমাতে হবে নাহলে পোস্ট সারাজীবন 'pending' থেকে যাবে
            $this->finalizeMediaProcessing($post, false); 
            throw $e;
        } finally {
            if (file_exists($this->tempPath)) unlink($this->tempPath);
        }
    }

    protected function finalizeMediaProcessing($post, $isSuccess)
    {
        // ১. প্রসেসিং শেষ হলে কাউন্ট কমানো
        $post->decrement('pending_media_count');

        // ২. যখন সব ইমেজের কাজ শেষ হবে (কাউন্ট ০)
        if ($post->fresh()->pending_media_count <= 0) {
            
            // ৩. চেক করুন ডাটাবেসে এই পোস্টের জন্য কোনো মিডিয়া সেভ হয়েছে কিনা
            $savedMediaCount = Post_media::where('post_id', $post->id)->count();
            
            if ($savedMediaCount > 0) {
                // যদি অন্তত ১টি ইমেজও ভ্যালিড থাকে, পোস্ট লাইভ হবে
                $post->update(['status' => 'active']);
                
                $this->sendFcmNotification(
                    $post->member_id,
                    "Your post is live! ✅",
                    "Processing complete. Some images may have been removed if they violated guidelines.",
                    ['post_id' => (string) $post->id, 'status' => 'active'],
                    'post',
                    (string) $post->id
                );
            } else {
                // যদি ১টিও ইমেজ সেভ না হয় (সবগুলো ১৮+ ছিল)
                $post->update(['status' => 'failed']); // অথবা $post->delete(); আপনার ইচ্ছা অনুযায়ী
                
                $this->sendFcmNotification(
                    $post->member_id,
                    "Post removed ⚠️",
                    "Your post was removed because all images violated our community guidelines.",
                    ['post_id' => (string) $post->id, 'status' => 'failed'],
                    'post'
                );
            }
        }
    }

    // public function handle()
    // {
    //     $post = Post::find($this->postId);

    //     if (!$post) {
    //         if (file_exists($this->tempPath)) unlink($this->tempPath);
    //         \Log::info("Post not found, skipping job: " . $this->postId);
    //         return;
    //     }

    //     $memberId = $post->member_id;

    //     try {
    //         $keyFileData = config('filesystems.disks.gcs.key_file');

    //         // ১. Vision API safety check
    //         $imageAnnotator = new ImageAnnotatorClient(['credentials' => $keyFileData]);
    //         $content        = file_get_contents($this->tempPath);
    //         $response       = $imageAnnotator->safeSearchDetection($content);
    //         $safe           = $response->getSafeSearchAnnotation();
    //         $imageAnnotator->close();

    //         if ($safe->getAdult() >= 4 || $safe->getRacy() >= 4) {
    //             \Log::warning("Inappropriate image detected for Post ID: {$this->postId}");
    //             try {
    //                 $this->sendFcmNotification(
    //                     $memberId,
    //                     "We removed your post ⚠️",
    //                     "Because it goes against our Community Standards.",
    //                     ['post_id' => (string) $this->postId, 'reason' => 'community_guidelines'],
    //                     'post'
    //                 );
    //             } catch (\Exception $e) {
    //                 \Log::error("FCM Rejection Notification Failed (Image): " . $e->getMessage());
    //             }
    //             $post->delete();
    //             return;
    //         }

    //         // ২. Image resize
    //         $img = Image::make($this->tempPath)->resize(1200, null, function ($constraint) {
    //             $constraint->aspectRatio();
    //             $constraint->upsize();
    //         })->encode('webp', 85);

    //         // ৩. GCS upload
    //         $storage  = new StorageClient([
    //             'projectId' => config('filesystems.disks.gcs.project_id'),
    //             'keyFile'   => $keyFileData,
    //         ]);
    //         $bucket   = $storage->bucket(config('filesystems.disks.gcs.bucket'));
    //         $fileName = "posts/images/{$this->fileNameBase}.webp";

    //         $bucket->upload((string) $img, [
    //             'name'     => $fileName,
    //             'metadata' => ['contentType' => 'image/webp'],
    //         ]);

    //         // ৪. Media record
    //         Post_media::create([
    //             'post_id'    => $this->postId,
    //             'media_type' => 'image',
    //             'path'       => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $fileName,
    //         ]);

    //         // ৫. Post active
    //         $post = Post::find($this->postId);
    //         if ($post) {
    //             $post->update(['status' => 'active']);
    //             try {
    //                 $this->sendFcmNotification(
    //                     $post->member_id,
    //                     "Your post is ready to view ✅",
    //                     "Your upload was successful and your post is now live.",
    //                     ['post_id' => (string) $post->id, 'status' => 'active'],
    //                     'post',
    //                     (string) $post->id
    //                 );
    //             } catch (\Exception $e) {
    //                 \Log::error("FCM Success Notification Failed (Image): " . $e->getMessage());
    //             }
    //         }

    //     } catch (\Exception $e) {
    //         \Log::error("Image Processing Error: " . $e->getMessage());
    //         throw $e; // re-throw — Laravel retry করবে
    //     } finally {
    //         if (file_exists($this->tempPath)) {
    //             unlink($this->tempPath);
    //         }
    //     }
    // }

    // ⚠️ সব retry শেষ হলে এই function call হবে
    public function failed(\Throwable $e): void
    {
        // ErrorLog-এ save
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

        // Critical mark + email
        ErrorLogService::jobFailed($error, $e);

        // Post failed করো + user notification
        $post = Post::find($this->postId);
        if ($post) {
            $post->update(['status' => 'failed']);
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