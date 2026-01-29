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

        $gcsUri = 'gs://' . config('filesystems.disks.gcs.bucket') . '/' . $this->videoPath;

        try {
            $operation = $videoClient->annotateVideo([
                'inputUri' => $gcsUri,
                'features' => [Feature::EXPLICIT_CONTENT_DETECTION],
            ]);

            $operation->pollUntilComplete();

            if ($operation->operationSucceeded()) {
                $results = $operation->getResult()->getAnnotationResults()[0];
                $explicitAnnotation = $results->getExplicitAnnotation();
                $isSafe = true;

                if ($explicitAnnotation) {
                    foreach ($explicitAnnotation->getFrames() as $frame) {
                        // Likelihood 4 = Likely, 5 = Very Likely (Adult Content)
                        if ($frame->getPornographyLikelihood() >= 4) {
                            $isSafe = false;
                            break;
                        }
                    }
                }

                $post = Post::find($this->postId);
                if ($post) {
                    if ($isSafe) {
                        // কন্টেন্ট সেফ হলে স্ট্যাটাস Active করে দিন
                        $post->update(['status' => 'active']);
                    } else {
                        // ১৮+ কন্টেন্ট থাকলে পোস্ট ডিলিট
                        $post->delete(); 
                        // ঐচ্ছিক: ইউজারকে নোটিফিকেশন পাঠানো যে তার পোস্ট কেন ডিলিট হয়েছে
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error("Video Intelligence Error: " . $e->getMessage());
        } finally {
            $videoClient->close();
        }
    }
}
