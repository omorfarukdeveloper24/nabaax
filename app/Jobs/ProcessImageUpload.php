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

class ProcessImageUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $postId, $tempPath, $fileNameBase;

    public function __construct($postId, $tempPath, $fileNameBase)
    {
        $this->postId = $postId;
        $this->tempPath = $tempPath;
        $this->fileNameBase = $fileNameBase;
    }

    public function handle()
    {
        try {
            $keyFileData = config('filesystems.disks.gcs.key_file');

            // ১. ইমেজ সেফটি চেক (Vision API)
            $imageAnnotator = new ImageAnnotatorClient(['credentials' => $keyFileData]);
            $content = file_get_contents($this->tempPath);
            $response = $imageAnnotator->safeSearchDetection($content);
            $safe = $response->getSafeSearchAnnotation();
            $imageAnnotator->close();

            // যদি অ্যাডাল্ট বা আপত্তিজনক কিছু পাওয়া যায় (Likelihood 4 = Likely, 5 = Very Likely)
            if ($safe->getAdult() >= 4 || $safe->getRacy() >= 4) {
                \Log::warning("Inappropriate image detected for Post ID: {$this->postId}. Deleting post.");
                
                // পোস্টটি ডিলিট করে দিন (ভিডিও জবের মতো)
                $post = Post::find($this->postId);
                if ($post) {
                    $post->delete();
                }
                return; // কাজ এখানেই শেষ
            }

            // ২. ইমেজ প্রসেসিং ও রিসাইজ
            $img = Image::make($this->tempPath)->resize(1200, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })->encode('webp', 85);

            // ৩. GCS-এ আপলোড
            $storage = new StorageClient(['projectId' => config('filesystems.disks.gcs.project_id'), 'keyFile' => $keyFileData]);
            $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));
            $fileName = "posts/images/{$this->fileNameBase}.webp";

            $bucket->upload((string)$img, [
                'name' => $fileName,
                'metadata' => ['contentType' => 'image/webp']
            ]);

            // ৪. মিডিয়া রেকর্ড তৈরি
            Post_media::create([
                'post_id' => $this->postId,
                'media_type' => 'image',
                'path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $fileName,
            ]);

            // ৫. সব ঠিক থাকলে স্ট্যাটাস একটিভ করুন
            $post = Post::find($this->postId);
            if ($post) {
                $post->update(['status' => 'active']);
            }

        } catch (\Exception $e) {
            \Log::error("Image Processing Error: " . $e->getMessage());
        } finally {
            if (file_exists($this->tempPath)) {
                unlink($this->tempPath);
            }
        }
    }
}
