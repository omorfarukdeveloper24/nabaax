<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Post;
use App\Models\Post_media;
use Illuminate\Support\Facades\Log;
use \App\Traits\NotificationTrait;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use FFMpeg\Format\Video\X264;

class ProcessVideoSafetyCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NotificationTrait;

    protected $postId;
    protected $videoPath;
    protected $customThumbPath;

    // টাইমআউট ০ মানে লারাভেল নিজ থেকে জব বন্ধ করবে না
    public $timeout = 0; 
    // ৩ বার চেষ্টা করবে
    public $tries = 3;

    public function __construct($postId, $videoPath, $customThumbPath = null)
    {
        $this->postId = $postId;
        $this->videoPath = $videoPath;
        $this->customThumbPath = $customThumbPath;
    }

    public function handle()
    {
        $post = Post::find($this->postId);
        
        // ফাইল আছে কি না চেক
        if (!$post || !file_exists($this->videoPath)) {
            Log::error("Job Aborted: File missing at " . $this->videoPath);
            return;
        }

        $fileNameBase = pathinfo($this->videoPath, PATHINFO_FILENAME);
        $compressedPath = storage_path('app/temp_videos/' . $fileNameBase . '_low.mp4');
        $thumbnailPath = storage_path('app/temp_videos/' . $fileNameBase . '_thumb.jpg');

        try {
            Log::info("Starting Video Processing: Post ID {$this->postId}");

            $ffmpeg = FFMpeg::fromDisk('local')->open('temp_videos/' . basename($this->videoPath));
            $durationInSeconds = $ffmpeg->getDurationInSeconds();
            
            // থাম্বনেইল
            if ($this->customThumbPath && file_exists($this->customThumbPath)) {
                copy($this->customThumbPath, $thumbnailPath);
            } else {
                $ffmpeg->getFrameFromSeconds(min(2, $durationInSeconds))
                    ->export()
                    ->toDisk('local')
                    ->save('temp_videos/' . basename($thumbnailPath));
            }

            // কম্প্রেশন (Threads ২ রাখা হয়েছে সার্ভারের সুরক্ষায়)
            $format = (new X264('libmp3lame', 'libx264'))->setKiloBitrate(1000); 
            $ffmpeg->export()
                ->toDisk('local')
                ->inFormat($format)
                ->addFilter('-crf', 28) 
                ->addFilter('-preset', 'veryfast') 
                ->addFilter('-threads', 2) 
                ->addFilter('-vf', 'scale=-2:720') 
                ->save('temp_videos/' . basename($compressedPath));
            
            Log::info("Compression Done for ID: {$this->postId}");

            // GCS আপলোড
            $storage = new \Google\Cloud\Storage\StorageClient([
                'projectId' => config('filesystems.disks.gcs.project_id'),
                'keyFile'    => config('filesystems.disks.gcs.key_file'),
            ]);
            $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

            $videoName = "posts/videos/" . basename($compressedPath);
            $thumbName = "posts/thumbnails/" . basename($thumbnailPath);

            $bucket->upload(fopen($compressedPath, 'r'), ['name' => $videoName, 'resumable' => true]);
            $bucket->upload(fopen($thumbnailPath, 'r'), ['name' => $thumbName]);

            // আপডেট ডাটাবেজ
            Post_media::updateOrCreate(
                ['post_id' => $post->id],
                [
                    'path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $videoName,
                    'media_type' => 'video',
                    'thumbnail_path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $thumbName,
                    'duration' => round($durationInSeconds),
                ]
            );
            
            $post->update(['status' => 'active']);
            $this->sendFcmNotification($post->member_id, "Video Ready!", "Your video is live.");

            // কাজ সফল হলে ফাইল ডিলিট
            $this->cleanupFiles($compressedPath, $thumbnailPath);

        } catch (\Exception $e) {
            Log::error("Job Attempt Failed (ID {$this->postId}): " . $e->getMessage());
            throw $e; // আবার ট্রাই করার জন্য এরর থ্রো করতে হবে
        }
    }

    // যদি সব চেষ্টার পরও জব ফেইল করে তবেই অরিজিনাল ফাইল ডিলিট হবে
    public function failed(\Throwable $exception)
    {
        Log::error("Job Permanently Failed: " . $exception->getMessage());
        $this->cleanupFiles();
    }

    protected function cleanupFiles($compressedPath = null, $thumbnailPath = null)
    {
        if (file_exists($this->videoPath)) @unlink($this->videoPath);
        if ($compressedPath && file_exists($compressedPath)) @unlink($compressedPath);
        if ($thumbnailPath && file_exists($thumbnailPath)) @unlink($thumbnailPath);
        if ($this->customThumbPath && file_exists($this->customThumbPath)) @unlink($this->customThumbPath);
    }
}