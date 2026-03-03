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

    public $timeout = 1800; // ৩০ মিনিট সময় দেওয়া হলো
    public $tries = 1;      // ২ জিবি র‍্যামের জন্য ১ বার ট্রাই করা নিরাপদ

    public function __construct($postId, $videoPath, $customThumbPath = null)
    {
        $this->postId = $postId;
        $this->videoPath = $videoPath;
        $this->customThumbPath = $customThumbPath;
    }

    public function handle()
    {
        $post = Post::find($this->postId);
        if (!$post || !file_exists($this->videoPath)) {
            $this->cleanupFiles();
            return;
        }

        // কনফিগ থেকে ডাটা নেওয়া
        $keyFileData = config('filesystems.disks.gcs.key_file');
        $bucketName = config('filesystems.disks.gcs.bucket');
        $projectId = config('filesystems.disks.gcs.project_id');

        try {
            Log::info("Safety Check Started for Post ID: {$this->postId}");

            // ১. ভিডিও ইন্টেলিজেন্স চেক (আপনার সফল হওয়া কোডের স্টাইল)
            $videoClient = new VideoIntelligenceServiceClient(['credentials' => $keyFileData]);
            
            // লোকাল ফাইল রিড করে সরাসরি চেক করা (তাড়াতাড়ি হয়)
            $operation = $videoClient->annotateVideo([
                'inputContent' => file_get_contents($this->videoPath),
                'features' => [Feature::EXPLICIT_CONTENT_DETECTION],
            ]);

            $operation->pollUntilComplete(['pollingIntervalSeconds' => 5]);

            $isSafe = true;
            if ($operation->operationSucceeded()) {
                $results = $operation->getResult()->getAnnotationResults()[0];
                $explicitAnnotation = $results->getExplicitAnnotation();

                if ($explicitAnnotation) {
                    foreach ($explicitAnnotation->getFrames() as $frame) {
                        // আপনার চাহিদা মতো শুধু Pornography এবং Very Likely (5) চেক
                        if ($frame->getPornographyLikelihood() >= 5) { 
                            $isSafe = false; 
                            break;
                        }
                    }
                }
            }
            $videoClient->close();

            // ২. যদি সেফ না হয় তবে ডিলিট
            if (!$isSafe) {
                Log::warning("Inappropriate video deleted. Post ID: {$this->postId}");
                $post->delete();
                $this->cleanupFiles();
                return;
            }

            // ৩. ভিডিও কম্প্রেশন এবং থাম্বনেইল (২ জিবি র‍্যামের জন্য অপ্টিমাইজড)
            $fileNameBase = pathinfo($this->videoPath, PATHINFO_FILENAME);
            $compressedPath = storage_path('app/temp_videos/' . $fileNameBase . '_low.mp4');
            $thumbnailPath = storage_path('app/temp_videos/' . $fileNameBase . '_thumb.jpg');

            $ffmpeg = FFMpeg::fromDisk('local')->open('temp_videos/' . basename($this->videoPath));
            $durationSeconds = $ffmpeg->getDurationInSeconds();

            // অটো থাম্বনেইল
            if ($this->customThumbPath && file_exists($this->customThumbPath)) {
                copy($this->customThumbPath, $thumbnailPath);
            } else {
                $ffmpeg->getFrameFromSeconds(min(1, $durationSeconds))
                    ->export()->toDisk('local')->save('temp_videos/' . basename($thumbnailPath));
            }

            // কম্প্রেশন (Ultrafast preset ২ জিবি র‍্যামের জন্য)
            $format = (new X264('aac', 'libx264'))->setKiloBitrate(700); 
            $ffmpeg->export()
                ->toDisk('local')
                ->inFormat($format)
                ->addFilter('-preset', 'ultrafast') 
                ->addFilter('-threads', 1) 
                ->addFilter('-vf', 'scale=-2:480') // রেজোলিউশন ৪৮০পি যাতে ফাস্ট হয়
                ->save('temp_videos/' . basename($compressedPath));

            // ৪. GCS আপলোড
            $storage = new \Google\Cloud\Storage\StorageClient([
                'projectId' => $projectId,
                'keyFile'    => $keyFileData,
            ]);
            $bucket = $storage->bucket($bucketName);

            $gcsVideoName = "posts/videos/" . basename($compressedPath);
            $gcsThumbName = "posts/thumbnails/" . basename($thumbnailPath);

            $bucket->upload(fopen($compressedPath, 'r'), ['name' => $gcsVideoName, 'resumable' => true]);
            $bucket->upload(fopen($thumbnailPath, 'r'), ['name' => $gcsThumbName]);

            // ৫. ডাটাবেজ আপডেট
            $mediaUrl = "https://storage.googleapis.com/{$bucketName}/{$gcsVideoName}";
            $thumbUrl = "https://storage.googleapis.com/{$bucketName}/{$gcsThumbName}";

            Post_media::updateOrCreate(
                ['post_id' => $post->id],
                [
                    'path' => $mediaUrl,
                    'media_type' => 'video',
                    'thumbnail_path' => $thumbUrl,
                    'duration' => round($durationSeconds),
                ]
            );

            $post->update(['status' => 'active']);
            $this->sendFcmNotification($post->member_id, "Video Ready! 🎬", "Your video is live now.");

            // লোকাল ফাইল ক্লিয়ার
            $this->cleanupFiles($compressedPath, $thumbnailPath);

        } catch (\Exception $e) {
            Log::error("Video Job Error (Post ID {$this->postId}): " . $e->getMessage());
            throw $e; 
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