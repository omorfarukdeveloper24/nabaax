<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Post;
use App\Services\ErrorLogService;

class UpdateCommentCountJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        private int    $postId,
        private string $action
    ) {}

    public function handle(): void
    {
        if ($this->action === 'increment') {
            Post::where('id', $this->postId)->increment('comment_count');
        } else {
            Post::where('id', $this->postId)
                ->where('comment_count', '>', 0)
                ->decrement('comment_count');
        }
    }

    public function failed(\Throwable $e): void
    {
        $error = ErrorLogService::log(
            type:      'job_failed',
            source:    'UpdateCommentCountJob',
            message:   $e->getMessage(),
            exception: $e,
            context:   [
                'post_id' => $this->postId,
                'action'  => $this->action,
            ],
            jobClass:  self::class,
            jobParams: [
                'postId' => $this->postId,
                'action' => $this->action,
            ],
            maxRetries: $this->tries
        );

        ErrorLogService::jobFailed($error, $e);
    }
}