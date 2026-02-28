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
use Illuminate\Support\Facades\Storage;

class ProcessVideoSafetyCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NotificationTrait;

    protected $postId;
    protected $videoPath;
    protected $customThumbPath;

    // বড় ভিডিওর জন্য টাইমআউট ২০ মিনিট
    public $timeout = 1200; 
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
            if (file_exists($this->videoPath)) unlink($this->videoPath);
            Log::info("Post ID {$this->postId} not found. Skipping video job.");
            return;
        }

        $fileNameBase = pathinfo($this->videoPath, PATHINFO_FILENAME);
        $compressedPath = storage_path('app/temp_videos/' . $fileNameBase . '_low.mp4');
        $thumbnailPath = storage_path('app/temp_videos/' . $fileNameBase . '_thumb.jpg');

        try {
            // --- ১. FFmpeg ব্যবহার করে ভিডিও কম্প্রেশন ও থাম্বনেইল জেনারেশন ---
            // ভিডিও কম্প্রেশন (Quality maintain করে সাইজ কমানো)
            FFMpeg::fromDisk('local')
                ->open('temp_videos/' . basename($this->videoPath))
                ->export()
                ->toDisk('local')
                ->inFormat(new X264('libx264', 'aac'))
                ->addFilter('-crf', 24) 
                ->save('temp_videos/' . basename($compressedPath));

            // অটো থাম্বনেইল জেনারেশন (৩ সেকেন্ড থেকে)
            

            if ($this->customThumbPath && file_exists($this->customThumbPath)) {
                // ইউজার যদি কাস্টম থাম্বনেইল দেয়, সেটা কপি করে নিয়ে আসা
                copy($this->customThumbPath, $thumbnailPath);
            } else {
                // ইউজার থাম্বনেইল না দিলে ভিডিও থেকে জেনারেট করা
                FFMpeg::fromDisk('local')
                    ->open('temp_videos/' . basename($this->videoPath))
                    ->getFrameFromSeconds(3)
                    ->export()
                    ->toDisk('local')
                    ->save('temp_videos/' . basename($thumbnailPath));
            }

            // --- ২. GCS আপলোড (কম্প্রেসড ভিডিও এবং থাম্বনেইল) ---
            $keyFileData = config('filesystems.disks.gcs.key_file');
            $videoName = "posts/videos/" . basename($compressedPath);
            $thumbName = "posts/thumbnails/" . basename($thumbnailPath);

            $storage = new \Google\Cloud\Storage\StorageClient([
                'projectId' => config('filesystems.disks.gcs.project_id'),
                'keyFile'    => $keyFileData,
            ]);
            $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

            // কম্প্রেসড ভিডিও আপলোড
            $bucket->upload(fopen($compressedPath, 'r'), ['name' => $videoName]);
            // থাম্বনেইল আপলোড
            $bucket->upload(fopen($thumbnailPath, 'r'), ['name' => $thumbName]);

            // --- ৩. আপনার আগের ভিডিও ইন্টেলিজেন্স চেক (অপরিবর্তিত) ---
            $videoClient = new VideoIntelligenceServiceClient(['credentials' => $keyFileData]);
            $gcsUri = 'gs://' . config('filesystems.disks.gcs.bucket') . '/' . $videoName;

            $operation = $videoClient->annotateVideo([
                'inputUri' => $gcsUri,
                'features' => [
                    Feature::EXPLICIT_CONTENT_DETECTION, 
                    Feature::LABEL_DETECTION 
                ],
            ]);

            $operation->pollUntilComplete(['pollingIntervalSeconds' => 5]);

            $isSafe = true;
            $durationSeconds = 0;

            if ($operation->operationSucceeded()) {
                $results = $operation->getResult()->getAnnotationResults()[0];

                // Duration Calculation (আপনার আগের লজিক)
                if ($results->getSegment()) {
                    $endTime = $results->getSegment()->getEndTimeOffset();
                    $durationSeconds = (int)$endTime->getSeconds() + ((int)$endTime->getNanos() / 1000000000);
                } 
                elseif ($results->getExplicitAnnotation()) {
                    $frames = $results->getExplicitAnnotation()->getFrames();
                    if (count($frames) > 0) {
                        $timeOffset = $frames[count($frames) - 1]->getTimeOffset();
                        $durationSeconds = (int)$timeOffset->getSeconds() + ((int)$timeOffset->getNanos() / 1000000000);
                    }
                }

                Log::info("Post ID: {$this->postId} - Raw Duration: " . $durationSeconds);

                // Explicit Content Check (আপনার আগের লজিক)
                $explicitAnnotation = $results->getExplicitAnnotation();
                if ($explicitAnnotation) {
                    foreach ($explicitAnnotation->getFrames() as $frame) {
                        if ($frame->getPornographyLikelihood() >= 5) { 
                            $isSafe = false; 
                            break;
                        }
                    }
                }
            }

            // --- ৪. ডাটাবেস আপডেট ও নোটিফিকেশন ---
            if ($post) {
                $memberId = $post->member_id;
                if ($isSafe) {
                    $mediaPath = "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $videoName;
                    $thumbnailUrl = "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $thumbName;

                    if ($durationSeconds <= 0) $durationSeconds = 1;

                    Post_media::updateOrCreate(
                        ['post_id' => $post->id, 'path' => $mediaPath],
                        [
                            'media_type' => 'video',
                            'duration'   => round($durationSeconds),
                            'thumbnail_path' => $thumbnailUrl // নতুন থাম্বনেইল পাথ
                        ]
                    );
                    
                    $post->update(['status' => 'active']);
                    Log::info("Video processed and saved for Post ID: {$this->postId}");
                    $this->sendFcmNotification($memberId, "Your video is ready 🎬", "Your video is now available for viewing.");

                } else {
                    $bucket->object($videoName)->delete();
                    $bucket->object($thumbName)->delete();
                    $post->delete(); 
                    Log::warning("Inappropriate video deleted for Post ID: {$this->postId}");
                    $this->sendFcmNotification($memberId, "Video removed ⚠️", "It goes against our Community Standards.");
                }
            }
            
            $videoClient->close();

        } catch (\Exception $e) {
            Log::error("Video Job Error (Post ID {$this->postId}): " . $e->getMessage());
            throw $e; 
        } finally {
            // সকল টেম্পোরারি ফাইল ক্লিনআপ
            if (file_exists($this->videoPath)) unlink($this->videoPath);
            if (isset($compressedPath) && file_exists($compressedPath)) unlink($compressedPath);
            if (isset($thumbnailPath) && file_exists($thumbnailPath)) unlink($thumbnailPath);
            if ($this->customThumbPath && file_exists($this->customThumbPath)) unlink($this->customThumbPath);
        }
    }
}