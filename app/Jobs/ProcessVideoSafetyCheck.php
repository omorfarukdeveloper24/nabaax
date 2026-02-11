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
        
        try {
            $storage = new \Google\Cloud\Storage\StorageClient([
                'projectId' => config('filesystems.disks.gcs.project_id'),
                'keyFile'    => $keyFileData,
            ]);
            $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

            // ১. ফাইলটি GCS-এ আপলোড (Stream upload for large files)
            $fileStream = fopen($this->videoPath, 'r');
            $bucket->upload($fileStream, [
                'name' => $fileName,
            ]);

            // ২. ভিডিও ইন্টেলিজেন্স চেক
            $videoClient = new VideoIntelligenceServiceClient(['credentials' => $keyFileData]);
            $gcsUri = 'gs://' . config('filesystems.disks.gcs.bucket') . '/' . $fileName;

            $operation = $videoClient->annotateVideo([
                'inputUri' => $gcsUri,
                'features' => [
                    Feature::EXPLICIT_CONTENT_DETECTION, 
                    Feature::LABEL_DETECTION 
                ],
            ]);

            // ৩. লুপ দিয়ে চেক করা যাতে কিউ ওয়ার্কার বুঝতে পারে কাজ চলছে
            $operation->pollUntilComplete([
                'pollingIntervalSeconds' => 5
            ]);

            $isSafe = true;
            $durationSeconds = 0; // ভিডিওর ডিউরেশন রাখার জন্য ভেরিয়েবল ডিফাইন করা হলো

            if ($operation->operationSucceeded()) {
                $results = $operation->getResult()->getAnnotationResults()[0];

                if ($results->getSegment()) {
                    $endTime = $results->getSegment()->getEndTimeOffset();
                    
                    // (int) কাস্টিং নিশ্চিত করুন কারণ Google API স্ট্রিং পাঠাতে পারে
                    $seconds = (int) $endTime->getSeconds();
                    $nanos = (int) $endTime->getNanos();
                    
                    $durationSeconds = $seconds + ($nanos / 1000000000);
                } 
                elseif ($results->getExplicitAnnotation()) {
                    $frames = $results->getExplicitAnnotation()->getFrames();
                    
                    if (count($frames) > 0) {
                        $lastFrame = $frames[count($frames) - 1];
                        $timeOffset = $lastFrame->getTimeOffset();
                        
                        $seconds = (int) $timeOffset->getSeconds();
                        $nanos = (int) $timeOffset->getNanos();
                        
                        $durationSeconds = $seconds + ($nanos / 1000000000);
                    }
                }

                // ডিবাগ করার জন্য একটি লগ অবশ্যই রাখুন
                Log::info("Post ID: {$this->postId} - Raw Duration: " . $durationSeconds);

                $explicitAnnotation = $results->getExplicitAnnotation();

                if ($explicitAnnotation) {
                    foreach ($explicitAnnotation->getFrames() as $frame) {
                        // ৪ এর বদলে ৫ ব্যবহার করুন। ৫ মানে 'Very Likely' বা নিশ্চিত।
                        // এছাড়া আপনি পর্নোগ্রাফি চেক করছেন, কিন্তু গুগল অনেক সময় 'Racy' ভিডিওকেও ধরে।
                        // আমরা শুধু পর্নোগ্রাফি ফিল্টার করব।
                        if ($frame->getPornographyLikelihood() >= 5) { 
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

                    if ($durationSeconds <= 0) {
                        Log::warning("Duration could not be calculated for Post ID: {$this->postId}. Defaulting to 1 for safety.");
                        $durationSeconds = 1; // অন্তত ০ যেন না থাকে
                    }
                    
                    // মিডিয়া টেবিল সেভ (এটি ট্রাই-ক্যাচ এর ভেতরে রাখাই নিরাপদ)
                    Post_media::create([
                        'post_id' => $post->id,
                        'media_type' => 'video',
                        'path' => $mediaPath,
                        'duration' => round($durationSeconds), // এখানে ডাটাবেসে রাউন্ড ফিগার সেকেন্ড সেভ করা হচ্ছে
                    ]);
                    
                    $post->update(['status' => 'active']);
                    Log::info("Video successfully processed and saved for Post ID: {$this->postId}");
                } else {
                    $bucket->object($fileName)->delete();
                    $post->delete(); 
                    Log::warning("Inappropriate video deleted for Post ID: {$this->postId}");
                }
            }
            
            $videoClient->close();

        } catch (\Exception $e) {
            Log::error("Video Job Error (Post ID {$this->postId}): " . $e->getMessage());
            // এরর হলেও যাতে কিউ বারবার ট্রাই না করে (বড় ফাইল বলে)
            throw $e; 
        } finally {
            if (file_exists($this->videoPath)) {
                unlink($this->videoPath);
            }
        }
    }


    
}