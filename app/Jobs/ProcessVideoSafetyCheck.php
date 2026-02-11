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

class ProcessVideoSafetyCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $postId;
    protected $videoPath;

    // বড় ভিডিওর জন্য টাইমআউট বাড়িয়ে ২০ মিনিট (১২০০ সেকেন্ড) করা হলো
    public $timeout = 1200; 

    public function __construct($postId, $videoPath)
    {
        $this->postId = $postId;
        $this->videoPath = $videoPath;
    }

    public function handle()
    {
        $keyFileData = config('filesystems.disks.gcs.key_file');
        $fileName = "posts/videos/" . basename($this->videoPath);
        
        // --- ১. ভিডিওর ডিউরেশন লোকালি বের করার কোড (নিখুঁত পদ্ধতির জন্য) ---
        $durationSeconds = 0;
        try {
            // সার্ভারে ffmpeg ইনস্টল থাকলে এটি দ্রুত ডিউরেশন দিয়ে দিবে
            $ffprobe = \FFMpeg\FFProbe::create();
            $durationSeconds = (float) $ffprobe->format($this->videoPath)->get('duration');
        } catch (\Exception $e) {
            Log::warning("FFMpeg duration extraction failed, will try Google API.");
        }

        try {
            $storage = new \Google\Cloud\Storage\StorageClient([
                'projectId' => config('filesystems.disks.gcs.project_id'),
                'keyFile'    => $keyFileData,
            ]);
            $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

            // ২. ফাইলটি GCS-এ আপলোড
            $fileStream = fopen($this->videoPath, 'r');
            $bucket->upload($fileStream, [
                'name' => $fileName,
            ]);

            // ৩. ভিডিও ইন্টেলিজেন্স চেক
            $videoClient = new VideoIntelligenceServiceClient(['credentials' => $keyFileData]);
            $gcsUri = 'gs://' . config('filesystems.disks.gcs.bucket') . '/' . $fileName;

            // এখানে Feature::LABEL_DETECTION যোগ করা হয়েছে যাতে ডিউরেশন ডাটা নিশ্চিত পাওয়া যায়
            $operation = $videoClient->annotateVideo([
                'inputUri' => $gcsUri,
                'features' => [
                    Feature::EXPLICIT_CONTENT_DETECTION,
                    Feature::LABEL_DETECTION 
                ],
            ]);

            $operation->pollUntilComplete([
                'pollingIntervalSeconds' => 5
            ]);

            $isSafe = true;
            if ($operation->operationSucceeded()) {
                $results = $operation->getResult()->getAnnotationResults()[0];

                // --- ৪. গুগল এপিআই থেকে ডিউরেশন ব্যাকআপ (যদি লোকাল ফেল করে) ---
                if ($durationSeconds <= 0 && $results->getSegment()) {
                    $endTime = $results->getSegment()->getEndTimeOffset();
                    $durationSeconds = $endTime->getSeconds() + ($endTime->getNanos() / 1000000000);
                }

                $explicitAnnotation = $results->getExplicitAnnotation();
                if ($explicitAnnotation) {
                    foreach ($explicitAnnotation->getFrames() as $frame) {
                        if ($frame->getPornographyLikelihood() >= 4) {
                            $isSafe = false; 
                            break;
                        }
                    }
                }
            }

            $post = Post::find($this->postId);
            if ($post) {
                if ($isSafe) {
                    $mediaPath = "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $fileName;
                    
                    Post_media::create([
                        'post_id' => $post->id,
                        'media_type' => 'video',
                        'path' => $mediaPath,
                        'duration' => round($durationSeconds), // ৫. ফাইনাল ডিউরেশন সেভ
                    ]);
                    
                    $post->update(['status' => 'active']);
                    Log::info("Video successfully processed. Duration: " . round($durationSeconds));
                } else {
                    $bucket->object($fileName)->delete();
                    $post->delete(); 
                    Log::warning("Inappropriate video deleted.");
                }
            }
            
            $videoClient->close();

        } catch (\Exception $e) {
            Log::error("Video Job Error: " . $e->getMessage());
            throw $e; 
        } finally {
            if (file_exists($this->videoPath)) {
                unlink($this->videoPath);
            }
        }
    }



}