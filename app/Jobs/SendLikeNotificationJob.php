<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Traits\NotificationTrait;

class SendLikeNotificationJob implements ShouldQueue
{
    use Queueable, NotificationTrait;

    public int $tries   = 3;
    public int $backoff = 15;

    public function __construct(
        private int    $postOwnerId,  // যার post-এ like হয়েছে
        private string $likerName,    // যে like করেছে
        private int    $postId,
        private string $reactionType, // 'like' অথবা 'dislike'
    ) {}

    public function handle(): void
    {
        // নিজের post-এ নিজে like করলে notification যাবে না
        // এই check LikeController থেকে করা হবে

        $title = $this->reactionType === 'like' ? 'New Like 👍' : 'New Dislike 👎';
        $body  = "{$this->likerName} reacted to your post.";

        $this->sendFcmNotification(
            $this->postOwnerId,
            $title,
            $body,
            [
                'type'    => $this->reactionType,
                'post_id' => (string) $this->postId,
            ]
        );
    }

    public function failed(\Throwable $e): void
    {
        \Log::error("SendLikeNotificationJob failed", [
            'post_owner_id' => $this->postOwnerId,
            'error'         => $e->getMessage(),
        ]);
    }
}