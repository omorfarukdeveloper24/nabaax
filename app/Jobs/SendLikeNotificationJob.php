<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Traits\NotificationTrait;
use App\Services\ErrorLogService;

class SendLikeNotificationJob implements ShouldQueue
{
    use Queueable, NotificationTrait;

    public int $tries   = 3;
    public int $backoff = 15;

    public function __construct(
        private int    $postOwnerId,
        private string $likerName,
        private int    $postId,
        private string $reactionType,
    ) {}

    public function handle(): void
    {
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
        $error = ErrorLogService::log(
            type:      'job_failed',
            source:    'SendLikeNotificationJob',
            message:   $e->getMessage(),
            exception: $e,
            context:   [
                'post_owner_id' => $this->postOwnerId,
                'post_id'       => $this->postId,
                'reaction_type' => $this->reactionType,
            ],
            jobClass:  self::class,
            jobParams: [
                'postOwnerId'  => $this->postOwnerId,
                'likerName'    => $this->likerName,
                'postId'       => $this->postId,
                'reactionType' => $this->reactionType,
            ],
            maxRetries: $this->tries
        );

        ErrorLogService::jobFailed($error, $e);
    }
}