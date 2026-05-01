<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Traits\NotificationTrait;
use App\Services\ErrorLogService;

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
        $error = ErrorLogService::log(
            type:      'job_failed',
            source:    'SendMentionNotificationJob',
            message:   $e->getMessage(),
            exception: $e,
            context:   [
                'mentioned_user_id' => $this->mentionedUserId,
                'post_id'           => $this->postId,
                'comment_id'        => $this->commentId,
            ],
            jobClass:  self::class,
            jobParams: [
                'mentionedUserId' => $this->mentionedUserId,
                'senderName'      => $this->senderName,
                'postId'          => $this->postId,
                'commentId'       => $this->commentId,
            ],
            maxRetries: $this->tries
        );

        ErrorLogService::jobFailed($error, $e);
    }
}