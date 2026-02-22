<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Deposit;
use App\Models\Member;
use App\Models\CustomerPayHistory;
use App\Models\AdminPayHistory;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Google\Client;
class DepositController extends Controller
{
   /**
     * Show deposit list with optional status filter
     */
    public function index(Request $request)
    {
        $status = $request->status ?? 'pending';
        $data = Deposit::with(['member' => function ($query) {
                $query->select('id', 'name'); 
            }])
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->latest()
            ->paginate(30);

        return view('backEnd.deposit.index', compact('data','status'));
    }



    // public function status(Request $request)
    // {
    //     $deposit = Deposit::find($request->hidden_id);
    //     $company = Company::first();

    //     if (!$deposit) {
    //         Toastr::error('Deposit not found!');
    //         return redirect()->back();
    //     }

    //     if (!$company) {
    //         Toastr::error('Company settings not found!');
    //         return redirect()->back();
    //     }

    //     $old_status = $deposit->status;
    //     $new_status = $request->status;

    //     try {
    //         DB::beginTransaction();

    //         $deposit->status = $new_status;
    //         $deposit->save();

    //         if ($new_status === 'approved' && $old_status !== 'approved') {
                
    //             $member = Member::find($deposit->member_id);

    //             if ($member) {
    //                 $member->increment('balance', $deposit->amount);

    //                 $company->increment('balance', $deposit->amount);

    //                 $transaction_id = 'DEP' . $deposit->tnx_id;

    //                 CustomerPayHistory::create([
    //                     'member_id'    => $member->id,
    //                     'payment_name' => 'Deposit Approved',
    //                     'tnx'          => $transaction_id,
    //                     'amount'       => $deposit->amount,
    //                     'balance'      => $member->balance, 
    //                     'method'       => $deposit->method ?? 'Manual', 
    //                     'type'         => 'credit',
    //                 ]);


    //             }
    //         }

    //         DB::commit();
    //         Toastr::success('Deposit marked as ' . $new_status . ' successfully');
    //         return redirect()->back();

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Toastr::error('Something went wrong. Please try again.');
    //         return redirect()->back();
    //     }
    // }



    public function status(Request $request)
    {
        $deposit = Deposit::find($request->hidden_id);
        $company = Company::first();

        if (!$deposit) {
            Toastr::error('Deposit not found!');
            return redirect()->back();
        }

        $old_status = $deposit->status;
        $new_status = $request->status;

        try {
            DB::beginTransaction();

            $deposit->status = $new_status;
            $deposit->save();

            if ($new_status === 'approved' && $old_status !== 'approved') {
                $member = Member::find($deposit->member_id);
                if ($member) {
                    $member->increment('balance', $deposit->amount);
                    $company->increment('balance', $deposit->amount);
                    
                    CustomerPayHistory::create([
                        'member_id'    => $member->id,
                        'payment_name' => 'Deposit Approved',
                        'tnx'          => 'DEP' . $deposit->tnx_id,
                        'amount'       => $deposit->amount,
                        'balance'      => $member->balance, 
                        'method'       => $deposit->method ?? 'Manual', 
                        'type'         => 'credit',
                    ]);
                }
            }

            DB::commit(); // লেনদেন আগে সফলভাবে শেষ করুন

            // লেনদেন সফল হওয়ার পর নোটিফিকেশন পাঠান
            // যেন নোটিফিকেশন সার্ভারে কোনো সমস্যা হলে ইউজারের ব্যালেন্স আপডেট রোলব্যাক না হয়
            try {
                $title = "Deposit Update";
                $body = "Your deposit of {$deposit->amount} has been " . ucfirst($new_status);
                
                $this->sendFcmNotification($deposit->member_id, $title, $body, [
                    'deposit_id' => (string)$deposit->id,
                    'status' => (string)$new_status
                ]);
            } catch (\Exception $e) {
                // নোটিফিকেশন না গেলে লগ রাখতে পারেন, কিন্তু ইউজারকে এরর দেখানোর দরকার নেই
                \Log::error("FCM Error: " . $e->getMessage());
            }

            Toastr::success('Deposit marked as ' . $new_status . ' successfully');
            return redirect()->back();

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Deposit Status Error: " . $e->getMessage());
            Toastr::error('Something went wrong. Please try again.');
            return redirect()->back();
        }
    }

    /**
     * প্রফেশনাল উপায়ে নোটিফিকেশন পাঠানোর জন্য আলাদা প্রাইভেট মেথড
     */
    private function sendFcmNotification($memberId, $title, $body, $data = [])
    {
        $deviceTokens = DB::table('device_tokens')
                        ->where('member_id', $memberId)
                        ->where('status', 1)
                        ->pluck('token')
                        ->unique();

        if ($deviceTokens->isEmpty()) {
            return; // টোকেন না থাকলে ফিরে যাবে
        }

        $accessToken = $this->getFcmAccessToken();
        $projectId = "nabaax-1fdde";

        foreach ($deviceTokens as $token) {
            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/$projectId/messages:send", [
                    "message" => [
                        "token" => $token,
                        "notification" => ["title" => $title, "body" => $body],
                        "data" => empty($data) ? null : $data
                    ]
                ]);

            // টোকেন ইনভ্যালিড হলে স্ট্যাটাস ০ করে দেওয়া
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

    
    // public function status(Request $request)
    // {
        
    //     $deposit = Deposit::find($request->hidden_id);

    //     if (!$deposit) {
    //         Toastr::error('Deposit not found!');
    //         return redirect()->back();
    //     }
        
        

    //     $old_status = $deposit->status;
    //     $deposit->status = $request->status;
    //     $deposit->save();

        
    //     if ($request->status === 'approved' && $old_status !== 'approved') {

    //         $member = Member::find($deposit->member_id);

    //         if ($member) {
    //             $member->balance += $deposit->amount;
    //             $member->save();
    //         }
    //     }

    //     Toastr::success('Deposit marked as ' . $request->status . ' successfully');
    //     return redirect()->back();
    // }

    
}
