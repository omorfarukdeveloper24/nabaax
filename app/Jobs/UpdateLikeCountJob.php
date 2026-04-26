<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Post;

class UpdateLikeCountJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        private int    $postId,
        private string $action,  // 'like_increment', 'like_decrement', 'dislike_increment', 'dislike_decrement'
    ) {}

    public function handle(): void
    {
        match($this->action) {
            'like_increment'      => Post::where('id', $this->postId)->increment('like_count'),
            'like_decrement'      => Post::where('id', $this->postId)->where('like_count', '>', 0)->decrement('like_count'),
            'dislike_increment'   => Post::where('id', $this->postId)->increment('dislike_count'),
            'dislike_decrement'   => Post::where('id', $this->postId)->where('dislike_count', '>', 0)->decrement('dislike_count'),
        };
    }

    public function failed(\Throwable $e): void
    {
        \Log::error("UpdateLikeCountJob failed", [
            'post_id' => $this->postId,
            'action'  => $this->action,
            'error'   => $e->getMessage(),
        ]);
    }
}