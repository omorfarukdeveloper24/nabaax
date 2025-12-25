<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;


class FcmService
{
    private Messaging $messaging;

    public function __construct()
    {
        $path = base_path(env('FIREBASE_CREDENTIALS_PATH'));
        $this->messaging = (new Factory)->withServiceAccount($path)->createMessaging();
    }

    public function sendToToken(string $token, string $title, string $body, array $data = []): string
    {
        $message = CloudMessage::withTarget('token', $token)
            ->withNotification(Notification::create($title, $body))
            ->withData($data);

        $res = $this->messaging->send($message); // string | array | object (version অনুযায়ী)
        return $this->normalizeMessageId($res);
    }

    public function sendToTopic(string $topic, string $title, string $body, array $data = []): string
    {
        $message = CloudMessage::withTarget('topic', $topic)
            ->withNotification(Notification::create($title, $body))
            ->withData($data);

        $res = $this->messaging->send($message); // string | array | object
        return $this->normalizeMessageId($res);
    }


public function sendToTokensSequential(array $tokens, string $title, string $body, array $data = []): array
{
    $tokens = array_values(array_unique(array_filter(array_map('strval', $tokens))));
    $ok=0; $fail=0; $failed=[];

    foreach ($tokens as $t) {
        try {
            $this->sendToToken($t, $title, $body, array_map('strval', $data));
            $ok++;
        } catch (\Throwable $e) {
            $fail++; $failed[] = $t;
        }
    }
    return ['success'=>$ok,'failure'=>$fail,'failed_tokens'=>$failed];
}







    private function normalizeMessageId($res): string
    {

        if (is_string($res)) {
            return $res;
        }

        if (is_array($res)) {
            $id = $res['name'] ?? $res['messageId'] ?? $res['message_id'] ?? null;
            if (is_string($id) && $id !== '') {
                return $id;
            }k)
            $first = reset($res);
            if (is_string($first) && $first !== '') {
                return $first;
            }
            // সর্বশেষ fallback
            return 'unknown-message-id';
        }

        if (is_object($res)) {
            if (method_exists($res, 'name')) {
                $id = $res->name();
                if (is_string($id) && $id !== '') return $id;
            }
            if (method_exists($res, 'messageId')) {
                $id = $res->messageId();
                if (is_string($id) && $id !== '') return $id;
            }
            if (method_exists($res, '__toString')) {
                $id = (string)$res;
                if ($id !== '') return $id;
            }
            return 'unknown-message-id';
        }
        return 'unknown-message-id';
    }
}
