<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Post;

class UpdateCommentCountJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        private int    $postId,
        private string $action  // 'increment' অথবা 'decrement'
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
        \Log::error("UpdateCommentCountJob failed", [
            'post_id' => $this->postId,
            'action'  => $this->action,
            'error'   => $e->getMessage(),
        ]);
    }
}