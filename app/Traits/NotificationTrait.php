<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Google\Client;

trait NotificationTrait
{
    public function sendFcmNotification($memberId, $title, $body, $data = [], $notificationType = '')
    {
        // ১. ডাটা ফরম্যাট করা (সব ভ্যালুকে স্ট্রিং-এ কনভার্ট করা)
        $formattedData = [];
        foreach ($data as $key => $value) {
            $formattedData[(string)$key] = (string)$value;
        }
        $formattedData['notification_type'] = (string)$notificationType;
        $formattedData['click_action'] = 'FLUTTER_NOTIFICATION_CLICK';

        // ২. ইউনিক একটিভ টোকেনগুলো সংগ্রহ করা
        $deviceTokens = DB::table('device_tokens')
                        ->where('member_id', $memberId)
                        ->where('status', 1)
                        ->pluck('token')
                        ->unique();

        if ($deviceTokens->isEmpty()) {
            return ['status' => false, 'message' => 'No active tokens found.'];
        }

        $accessToken = $this->getFcmAccessToken();
        $projectId = "nabaax-1fdde"; 
        $isSent = false; 
        $responses = [];

        // ৩. প্রতিটি টোকেনে নোটিফিকেশন পাঠানো
        foreach ($deviceTokens as $token) {
            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/$projectId/messages:send", [
                    "message" => [
                        "token" => $token,
                        "notification" => [
                            "title" => $title,
                            "body"  => $body
                        ],
                        "data" => empty($formattedData) ? null : $formattedData
                    ]
                ]);

            $responseData = $response->json();

            if ($response->successful()) {
                $isSent = true; // অন্তত একটি সফল হয়েছে
            }

            // ৪. ইনভ্যালিড বা আনরেজিস্টার্ড টোকেন ডিজেবল করা
            if ($response->failed() && isset($responseData['error']['details'][0]['errorCode'])) {
                if ($responseData['error']['details'][0]['errorCode'] == 'UNREGISTERED') {
                    DB::table('device_tokens')->where('token', $token)->update(['status' => 0]);
                }
            }

            $responses[] = [
                'token' => substr($token, 0, 15) . '...',
                'status' => $response->successful() ? 'Success' : 'Failed'
            ];
        }

        // ৫. নোটিফিকেশন সফল হলে ডাটাবেসে রেকর্ড রাখা
        if ($isSent) {
            DB::table('notifications')->insert([
                'member_id'         => $memberId,
                'firebase_id'       => null, 
                'title'             => $title,
                'description'       => $body,
                'notification_type' => $notificationType,
                'status'            => 0, // ডিফল্ট আনরিড
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }

        return [
            'status' => $isSent,
            'total_tokens' => count($deviceTokens),
            'results' => $responses
        ];
    }

    private function getFcmAccessToken()
    {
        $client = new Client();
        $client->setAuthConfig(storage_path('app/firebase.json'));
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->refreshTokenWithAssertion();
        $token = $client->getAccessToken();
        return $token['access_token'];
    }
}