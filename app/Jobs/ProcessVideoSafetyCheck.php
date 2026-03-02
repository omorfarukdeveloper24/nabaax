<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Google\Cloud\VideoIntelligence\V1\VideoIntelligenceServiceClient;
use Google\Cloud\VideoIntelligence\V1\Feature;
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

    public $timeout = 3600; 
    public $tries = 1; // ৪ জিবি র‍্যামের জন্য ১ বার চেষ্টা করাই নিরাপদ, নাহলে মেমরি জ্যাম হবে

    public function __construct($postId, $videoPath, $customThumbPath = null)
    {
        $this->postId = $postId;
        $this->videoPath = $videoPath;
        $this->customThumbPath = $customThumbPath;
    }

    public function handle()
    {
        $post = Post::find($this->postId);
        // অরিজিনাল ফাইল আছে কি না চেক
        if (!$post || !file_exists($this->videoPath)) {
            Log::error("Job Aborted: Post not found or Video file missing at " . $this->videoPath);
            return;
        }

        $fileNameBase = pathinfo($this->videoPath, PATHINFO_FILENAME);
        $videoBaseName = basename($this->videoPath);
        $compressedPath = storage_path('app/temp_videos/' . $fileNameBase . '_low.mp4');
        $thumbnailPath = storage_path('app/temp_videos/' . $fileNameBase . '_thumb.jpg');

        try {
            Log::info("Starting Video Processing: Post ID {$this->postId}");

            // ১. ডিউরেশন বের করা
            $ffmpeg = FFMpeg::fromDisk('local')->open('temp_videos/' . $videoBaseName);
            $durationInSeconds = $ffmpeg->getDurationInSeconds();
            
            // ২. থাম্বনেইল তৈরি (কম্প্রেশনের আগে করলে এরর কম হয়)
            if ($this->customThumbPath && file_exists($this->customThumbPath)) {
                copy($this->customThumbPath, $thumbnailPath);
            } else {
                $ffmpeg->getFrameFromSeconds(min(2, $durationInSeconds))
                    ->export()
                    ->toDisk('local')
                    ->save('temp_videos/' . basename($thumbnailPath));
            }

            // ৩. ভিডিও কম্প্রেশন (threads কমিয়ে ২ করা হয়েছে যাতে ৪জিবি র‍্যামে হ্যাং না হয়)
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

            // ৪. GCS আপলোড
            $storage = new \Google\Cloud\Storage\StorageClient([
                'projectId' => config('filesystems.disks.gcs.project_id'),
                'keyFile'    => config('filesystems.disks.gcs.key_file'),
            ]);
            $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

            $videoName = "posts/videos/" . basename($compressedPath);
            $thumbName = "posts/thumbnails/" . basename($thumbnailPath);

            // রিসুমেবল আপলোড
            $bucket->upload(fopen($compressedPath, 'r'), [
                'name' => $videoName,
                'resumable' => true
            ]);
            $bucket->upload(fopen($thumbnailPath, 'r'), ['name' => $thumbName]);

            // ৫. ডাটাবেজ আপডেট (ইন্টেলিজেন্স চেক এখানে অপশনাল বা পরে করতে পারেন)
            $mediaUrl = "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $videoName;
            $thumbUrl = "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $thumbName;

            Post_media::updateOrCreate(
                ['post_id' => $post->id],
                [
                    'path' => $mediaUrl,
                    'media_type' => 'video',
                    'thumbnail_path' => $thumbUrl,
                    'duration' => round($durationInSeconds),
                ]
            );
            $post->update(['status' => 'active']);
            $this->sendFcmNotification($post->member_id, "Video Ready!", "Your video is live now.");

            // সব সফল হলে ফাইল ক্লিয়ার করুন
            $this->cleanupFiles($compressedPath, $thumbnailPath);

        } catch (\Exception $e) {
            Log::error("Job Failed (Post ID {$this->postId}): " . $e->getMessage());
            // এখানে ডিলিট করবেন না, যাতে পরের বার ট্রাই করলে ফাইল পায়
            throw $e; 
        }
    }

    protected function cleanupFiles($compressedPath = null, $thumbnailPath = null)
    {
        // অরিজিনাল ফাইল
        if (file_exists($this->videoPath)) @unlink($this->videoPath);
        // কম্প্রেসড ফাইল
        if ($compressedPath && file_exists($compressedPath)) @unlink($compressedPath);
        // থাম্বনেইল ফাইল
        if ($thumbnailPath && file_exists($thumbnailPath)) @unlink($thumbnailPath);
        // কাস্টম থাম্ব সোর্স
        if ($this->customThumbPath && file_exists($this->customThumbPath)) @unlink($this->customThumbPath);
    }
}