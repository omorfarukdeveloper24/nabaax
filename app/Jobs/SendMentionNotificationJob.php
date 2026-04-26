<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Traits\NotificationTrait;

class SendMentionNotificationJob implements ShouldQueue
{
    use Queueable, NotificationTrait;

    public int $tries   = 3;
    public int $backoff = 15;

    public function __construct(
        private int    $mentionedUserId,
        private string $senderName,
        private int    $postId,
        private int    $commentId,
    ) {}

    public function handle(): void
    {
        $this->sendFcmNotification(
            $this->mentionedUserId,
            'New Mention',
            "{$this->senderName} mentioned you in a comment.",
            [
                'type'       => 'mention',
                'post_id'    => (string) $this->postId,
                'comment_id' => (string) $this->commentId,
            ]
        );
    }

    public function failed(\Throwable $e): void
    {
        \Log::error("SendMentionNotificationJob failed", [
            'user_id' => $this->mentionedUserId,
            'error'   => $e->getMessage(),
        ]);
    }
}