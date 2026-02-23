<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Google\Client;

trait NotificationTrait
{
    
    public function sendFcmNotification($memberId, $title, $body, $data = [])
    {
        
        DB::table('notifications')->insert([
            'member_id'   => $memberId,
            'title'       => $title,
            'description' => $body,
            'status'      => 0, 
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        
        $deviceTokens = DB::table('device_tokens')
                        ->where('member_id', $memberId)
                        ->where('status', 1)
                        ->pluck('token')
                        ->unique();

        if ($deviceTokens->isEmpty()) {
            return;
        }

        
        $accessToken = $this->getFcmAccessToken();
        $projectId = "nabaax-1fdde"; 

        
        foreach ($deviceTokens as $token) {
            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/$projectId/messages:send", [
                    "message" => [
                        "token" => $token,
                        "notification" => [
                            "title" => $title, 
                            "body"  => $body
                        ],
                        
                        "data" => empty($data) ? null : array_map('strval', $data)
                    ]
                ]);

            
            if ($response->failed()) {
                $responseData = $response->json();
                if (isset($responseData['error']['details'][0]['errorCode']) && 
                    $responseData['error']['details'][0]['errorCode'] == 'UNREGISTERED') {
                    DB::table('device_tokens')->where('token', $token)->update(['status' => 0]);
                }
            }
        }
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