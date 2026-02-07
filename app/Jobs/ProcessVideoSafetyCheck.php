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

class ProcessVideoSafetyCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $postId;
    protected $videoPath;

    public function __construct($postId, $videoPath)
    {
        $this->postId = $postId;
        $this->videoPath = $videoPath;
    }

    public function handle()
    {
        $keyFileData = config('filesystems.disks.gcs.key_file');
        $storage = new \Google\Cloud\Storage\StorageClient([
            'projectId' => config('filesystems.disks.gcs.project_id'),
            'keyFile'    => $keyFileData,
        ]);
        $bucket = $storage->bucket(config('filesystems.disks.gcs.bucket'));

        $fileName = "posts/videos/" . basename($this->videoPath);
        
        try {
            // ১. আগে ভিডিওটি GCS-এ আপলোড করুন
            $bucket->upload(fopen($this->videoPath, 'r'), [
                'name' => $fileName,
            ]);

            // ২. ভিডিও ইন্টেলিজেন্স চেক
            $videoClient = new \Google\Cloud\VideoIntelligence\V1\VideoIntelligenceServiceClient(['credentials' => $keyFileData]);
            $gcsUri = 'gs://' . config('filesystems.disks.gcs.bucket') . '/' . $fileName;

            $operation = $videoClient->annotateVideo([
                'inputUri' => $gcsUri,
                'features' => [\Google\Cloud\VideoIntelligence\V1\Feature::EXPLICIT_CONTENT_DETECTION],
            ]);

            $operation->pollUntilComplete();
            $isSafe = true;

            if ($operation->operationSucceeded()) {
                $results = $operation->getResult()->getAnnotationResults()[0];
                $explicitAnnotation = $results->getExplicitAnnotation();

                if ($explicitAnnotation) {
                    foreach ($explicitAnnotation->getFrames() as $frame) {
                        if ($frame->getPornographyLikelihood() >= 4) {
                            $isSafe = false; break;
                        }
                    }
                }
            }

            $post = Post::find($this->postId);
            if ($post) {
                if ($isSafe) {
                    // সব ঠিক থাকলে মিডিয়া রেকর্ড সেভ এবং স্ট্যাটাস Active
                    $mediaPath = "https://storage.googleapis.com/" . config('filesystems.disks.gcs.bucket') . "/" . $fileName;
                    \App\Models\Post_media::create([
                        'post_id' => $post->id,
                        'media_type' => 'video',
                        'path' => $mediaPath,
                    ]);
                    $post->update(['status' => 'active']);
                } else {
                    // ১৮+ হলে পোস্ট ডিলিট এবং GCS থেকে ফাইল ডিলিট
                    $bucket->object($fileName)->delete();
                    $post->delete(); 
                }
            }
            
            $videoClient->close();

        } catch (\Exception $e) {
            \Log::error("Video Job Error: " . $e->getMessage());
        } finally {
            // টেম্পোরারি ফাইল ডিলিট করা
            if (file_exists($this->videoPath)) {
                unlink($this->videoPath);
            }
        }
    }
}
