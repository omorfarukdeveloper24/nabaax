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
        $videoClient = new VideoIntelligenceServiceClient(['credentials' => $keyFileData]);

        // GCS এর Path (e.g., gs://bucket-name/posts/videos/abc.mp4)
        $gcsUri = 'gs://' . config('filesystems.disks.gcs.bucket') . '/' . $this->videoPath;

        $operation = $videoClient->annotateVideo([
            'inputUri' => $gcsUri,
            'features' => [Feature::EXPLICIT_CONTENT_DETECTION],
        ]);

        $operation->pollUntilComplete();

        if ($operation->operationSucceeded()) {
            $results = $operation->getResult()->getAnnotationResults()[0];
            $explicitAnnotation = $results->getExplicitAnnotation();

            foreach ($explicitAnnotation->getFrames() as $frame) {
                if ($frame->getPornographyLikelihood() >= 4) {
                    // যদি ১৮+ কন্টেন্ট পাওয়া যায়, পোস্ট ডিলিট বা হাইড করবেন
                    $post = Post::find($this->postId);
                    if($post) {
                        // আপনার লজিক অনুযায়ী একশন নিন (যেমন: ডিলিট করা)
                        $post->delete(); 
                    }
                    break;
                }
            }
        }
        $videoClient->close();
    }
}
