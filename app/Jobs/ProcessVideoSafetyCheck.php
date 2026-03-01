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
        if (!$post) {
            $this->cleanupFiles();
            return;
        }

        $fileNameBase = pathinfo($this->videoPath, PATHINFO_FILENAME);
        $compressedPath = storage_path('app/temp_videos/' . $fileNameBase . '_low.mp4');
        $thumbnailPath = storage_path('app/temp_videos/' . $fileNameBase . '_thumb.jpg');

        try {
            Log::info("Starting Optimized Compression for Post ID: {$this->postId}");
            
            // ১. ভিডিওর ডিউরেশন বের করা (Duration Extraction)
            // এটি ভিডিও কম্প্রেস হওয়ার আগেই অরিজিনাল ফাইল থেকে ডিউরেশন নিয়ে নেবে
            $ffmpegData = FFMpeg::fromDisk('local')->open('temp_videos/' . basename($this->videoPath));
            $durationInSeconds = $ffmpegData->getDurationInSeconds();
            Log::info("Video Duration detected: {$durationInSeconds} seconds.");

            // ২. অপ্টিমাইজড কম্প্রেশন (Messenger/WhatsApp Style)
            $format = (new X264('libmp3lame', 'libx264'))->setKiloBitrate(1000); 
            
            $ffmpegData->export()
                ->toDisk('local')
                ->inFormat($format)
                ->addFilter('-crf', 28) 
                ->addFilter('-preset', 'veryfast') 
                ->addFilter('-vf', 'scale=-2:720') 
                ->save('temp_videos/' . basename($compressedPath));
            
            // ৩. থাম্বনেইল লজিক
            if ($this->customThumbPath && file_exists($this->customThumbPath)) {
                copy($this->customThumbPath, $thumbnailPath);
            } else {
                $ffmpegData->getFrameFromSeconds(min(2, $durationInSeconds))
                    ->export()
                    ->toDisk('local')
                    ->save('temp_videos/' . basename($thumbnailPath));
            }

            // ৪. GCS আপলোড (Resumable Mode)
            $storage = new \Google\Cloud\Storage\StorageClient([
                'projectId' => config('filesystems.disks.gcs.project_id'),
                'keyFile'    => config('filesystems.disks.gcs.key_file'),
            ]);
            $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

            $videoName = "posts/videos/" . basename($compressedPath);
            $thumbName = "posts/thumbnails/" . basename($thumbnailPath);

            $bucket->upload(fopen($compressedPath, 'r'), [
                'name' => $videoName,
                'resumable' => true, 
                'chunkSize' => 262144 * 4 
            ]);

            $bucket->upload(fopen($thumbnailPath, 'r'), ['name' => $thumbName]);

            // ৫. ভিডিও ইন্টেলিজেন্স চেক
            $videoClient = new VideoIntelligenceServiceClient(['credentials' => config('filesystems.disks.gcs.key_file')]);
            $gcsUri = 'gs://' . config('filesystems.disks.gcs.bucket') . '/' . $videoName;

            $operation = $videoClient->annotateVideo([
                'inputUri' => $gcsUri,
                'features' => [Feature::EXPLICIT_CONTENT_DETECTION],
            ]);

            $operation->pollUntilComplete(['pollingIntervalSeconds' => 5]);

            $isSafe = true;
            if ($operation->operationSucceeded()) {
                $results = $operation->getResult()->getAnnotationResults()[0];
                $explicit = $results->getExplicitAnnotation();
                if ($explicit) {
                    foreach ($explicit->getFrames() as $frame) {
                        if ($frame->getPornographyLikelihood() >= 5) { 
                            $isSafe = false; 
                            break;
                        }
                    }
                }
            }

            // ৬. ডাটাবেজ আপডেট (With Duration)
            if ($isSafe) {
                $mediaUrl = "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $videoName;
                $thumbUrl = "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $thumbName;

                Post_media::updateOrCreate(
                    ['post_id' => $post->id],
                    [
                        'path' => $mediaUrl,
                        'media_type' => 'video',
                        'thumbnail_path' => $thumbUrl,
                        'duration' => round($durationInSeconds), // এখানে ডিউরেশন সেভ হচ্ছে
                    ]
                );
                $post->update(['status' => 'active']);
                $this->sendFcmNotification($post->member_id, "Your video is ready 🎬", "Your video is now available for viewing.");
            } else {
                $bucket->object($videoName)->delete();
                $post->delete();
                Log::warning("SafeSearch: Content not safe. Post ID: {$this->postId} deleted.");
            }

            $videoClient->close();

        } catch (\Exception $e) {
            Log::error("Job Error (Post ID {$this->postId}): " . $e->getMessage());
            throw $e; 
        } finally {
            $this->cleanupFiles($compressedPath, $thumbnailPath);
        }
    }

    protected function cleanupFiles($compressedPath = null, $thumbnailPath = null)
    {
        if (file_exists($this->videoPath)) @unlink($this->videoPath);
        if ($compressedPath && file_exists($compressedPath)) @unlink($compressedPath);
        if ($thumbnailPath && file_exists($thumbnailPath)) @unlink($thumbnailPath);
        if ($this->customThumbPath && file_exists($this->customThumbPath)) @unlink($this->customThumbPath);
    }
}