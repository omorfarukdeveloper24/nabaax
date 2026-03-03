<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Google\Cloud\VideoIntelligence\V1\VideoIntelligenceServiceClient;
use Google\Cloud\VideoIntelligence\V1\Feature;
use Google\Cloud\VideoIntelligence\V1\Likelihood;
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

    public $timeout = 0; 
    public $tries = 2;   

    public function __construct($postId, $videoPath, $customThumbPath = null)
    {
        $this->postId = $postId;
        $this->videoPath = $videoPath;
        $this->customThumbPath = $customThumbPath;
    }

    public function handle()
    {
        $post = Post::find($this->postId);
        if (!$post || !file_exists($this->videoPath)) return;

        try {
            Log::info("Safety Check & Processing Started: Post ID {$this->postId}");

            // ১. Google Video Intelligence দিয়ে Adult Content চেক
            $isSafe = $this->checkVideoSafety($this->videoPath);
            
            if (!$isSafe) {
                Log::warning("Video Deleted: Adult content detected in Post ID {$this->postId}");
                $post->delete(); 
                $this->cleanupFiles();
                return;
            }

            // ২. থাম্বনেইল ও কম্প্রেশন শুরু (যদি সেফ হয়)
            $fileNameBase = pathinfo($this->videoPath, PATHINFO_FILENAME);
            $compressedPath = storage_path('app/temp_videos/' . $fileNameBase . '_low.mp4');
            $thumbnailPath = storage_path('app/temp_videos/' . $fileNameBase . '_thumb.jpg');

            $ffmpeg = FFMpeg::fromDisk('local')->open('temp_videos/' . basename($this->videoPath));
            $duration = (int) $ffmpeg->getDurationInSeconds();

            // অটো থাম্বনেইল
            if ($this->customThumbPath && file_exists($this->customThumbPath)) {
                copy($this->customThumbPath, $thumbnailPath);
            } else {
                $ffmpeg->getFrameFromSeconds(min(1, $duration))
                    ->export()->toDisk('local')->save('temp_videos/' . basename($thumbnailPath));
            }

            // ৩. ফাস্ট কম্প্রেশন (২ জিবি র‍্যামের জন্য ৪৮০পি)
            $format = (new X264('aac', 'libx264'))->setKiloBitrate(600); 
            $ffmpeg->export()
                ->toDisk('local')
                ->inFormat($format)
                ->addFilter('-preset', 'ultrafast') 
                ->addFilter('-threads', 1) 
                ->addFilter('-vf', 'scale=-2:480') 
                ->save('temp_videos/' . basename($compressedPath));
            
            // ৪. GCS আপলোড
            $this->uploadToGCS($compressedPath, $thumbnailPath, $post, $duration);

            $this->cleanupFiles($compressedPath, $thumbnailPath);

        } catch (\Exception $e) {
            Log::error("Job Failed (Post ID {$this->postId}): " . $e->getMessage());
            throw $e; 
        }
    }

    // Google Safety Check Function
    private function checkVideoSafety($path)
    {
        $videoIntelligence = new VideoIntelligenceServiceClient([
            'keyFile' => config('filesystems.disks.gcs.key_file'),
        ]);

        $operation = $videoIntelligence->annotateVideo([
            'inputContent' => file_get_contents($path),
            'features' => [Feature::EXPLICIT_CONTENT_DETECTION],
        ]);

        $operation->pollUntilComplete();
        if ($operation->operationSucceeded()) {
            $results = $operation->getResult()->getExplicitAnnotation();
            foreach ($results->getFrames() as $frame) {
                // এখানে চেক করছি যদি Pornography বা Adult কন্টেন্ট 'VERY_LIKELY' (5) হয়
                if ($frame->getPornographyLikelihood() >= Likelihood::VERY_LIKELY) {
                    return false; // ভিডিওটি নিরাপদ নয়
                }
            }
        }
        return true; // ভিডিওটি নিরাপদ
    }

    private function uploadToGCS($compressedPath, $thumbnailPath, $post, $duration)
    {
        $storage = new \Google\Cloud\Storage\StorageClient([
            'projectId' => config('filesystems.disks.gcs.project_id'),
            'keyFile'    => config('filesystems.disks.gcs.key_file'),
        ]);
        $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

        $videoName = "posts/videos/" . basename($compressedPath);
        $thumbName = "posts/thumbnails/" . basename($thumbnailPath);

        $bucket->upload(fopen($compressedPath, 'r'), ['name' => $videoName, 'resumable' => true]);
        $bucket->upload(fopen($thumbnailPath, 'r'), ['name' => $thumbName]);

        Post_media::updateOrCreate(
            ['post_id' => $post->id],
            [
                'path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $videoName,
                'media_type' => 'video',
                'thumbnail_path' => "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $thumbName,
                'duration' => $duration,
            ]
        );

        $post->update(['status' => 'active']);
        $this->sendFcmNotification($post->member_id, "Video Ready!", "Your video is live.");
    }

    protected function cleanupFiles($compressedPath = null, $thumbnailPath = null)
    {
        if (file_exists($this->videoPath)) @unlink($this->videoPath);
        if ($compressedPath && file_exists($compressedPath)) @unlink($compressedPath);
        if ($thumbnailPath && file_exists($thumbnailPath)) @unlink($thumbnailPath);
        if ($this->customThumbPath && file_exists($this->customThumbPath)) @unlink($this->customThumbPath);
    }
}