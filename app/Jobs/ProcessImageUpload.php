<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Intervention\Image\Facades\Image;
use Google\Cloud\Storage\StorageClient;
use App\Models\Post_media;

class ProcessImageUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $postId;
    protected $tempPath;
    protected $fileNameBase;

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
            $storage = new StorageClient([
                'projectId' => config('filesystems.disks.gcs.project_id'),
                'keyFile'    => $keyFileData,
            ]);
            $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

            // ১. ইমেজ প্রসেসিং (Resize and Encode to WebP)
            $img = Image::make($this->tempPath)->resize(1200, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })->encode('webp', 85);

            $fileName = "posts/images/{$this->fileNameBase}.webp";

            // ২. GCS-এ আপলোড
            $bucket->upload((string)$img, [
                'name' => $fileName,
                'metadata' => ['contentType' => 'image/webp']
            ]);

            // ৩. ডাটাবেসে রেকর্ড সেভ করা
            Post_media::create([
                'post_id' => $this->postId,
                'media_type' => 'image',
                'path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $fileName,
            ]);

        } catch (\Exception $e) {
            \Log::error("Image Processing Error: " . $e->getMessage());
        } finally {
            // টেম্পোরারি ফাইল ডিলিট
            if (file_exists($this->tempPath)) {
                unlink($this->tempPath);
            }
        }
    }
}
