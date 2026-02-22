<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WalletWithdraw;
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

class WithdrawController extends Controller
{
    /**
     * Show withdraw list with optional status filter
     */
    public function index(Request $request)
    {
        $status = $request->status ?? 'pending';
        $data = WalletWithdraw::with(['member' => function ($query) {
                $query->select('id', 'name'); 
            }])
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->latest()
            ->paginate(30);

        return view('backEnd.withdraw.index', compact('data','status'));
    }

    

    // public function status(Request $request)
    // {
    //     $withdraw = WalletWithdraw::find($request->hidden_id);

    //     if (!$withdraw) {
    //         Toastr::error('Withdraw not found!');
    //         return redirect()->back();
    //     }

    //     $old_status = $withdraw->status;
    //     $new_status = $request->status;

    //     $withdraw->status = $new_status;
    //     $withdraw->save();

    //     $member = Member::find($withdraw->member_id);

    //     if ($member) {
    //         if ($new_status === 'approved' && $old_status !== 'approved') {
    //             Toastr::success('Withdraw approved and successfully processed.');
    //             return redirect()->back();
    //         } 
    //         elseif ($new_status === 'rejected' && $old_status !== 'rejected') {
    //             $member->balance += $withdraw->amount; 
    //             $member->save();
                
    //             Toastr::error('Withdraw rejected and amount refunded.'); 
    //             return redirect()->back();
    //         }
    //     }

        
    //     Toastr::info('Withdraw status updated to ' . $new_status);
    //     return redirect()->back();
    // }


//      public function status(Request $request)
// {
//         $withdraw = WalletWithdraw::find($request->hidden_id);
//         $company = Company::first();

//         if (!$withdraw) {
//             Toastr::error('Withdraw not found!');
//             return redirect()->back();
//         }

//         $old_status = $withdraw->status;
//         $new_status = $request->status;
//         $member = Member::find($withdraw->member_id);

//         if (!$member) {
//             Toastr::error('Member not found!');
//             return redirect()->back();
//         }

//         if (!$company) {
//             Toastr::error('Company settings not found!');
//             return redirect()->back();
//         }

//         try {
//             DB::beginTransaction();

//             $withdraw->status = $new_status;
//             $withdraw->save();



//             if ($new_status === 'approved' && $old_status !== 'approved') {

//                 $company->decrement('balance', $withdraw->amount);
                
//                 $transaction_id = 'WTH' . now()->format('ymdHis') . strtoupper(Str::random(3));

//                 CustomerPayHistory::create([
//                     'member_id'    => $member->id,
//                     'payment_name' => 'Withdraw Approved',
//                     'tnx'          => $transaction_id,
//                     'amount'       => $withdraw->amount,
//                     'balance'      => $member->balance, 
//                     'method'       => $withdraw->method ?? 'Wallet',
//                     'type'         => 'debit',
//                 ]);

                
//                 AdminPayHistory::create([
//                     'member_id'    => $member->id,
//                     'payment_name' => 'Withdraw Approved for ' . $member->name,
//                     'tnx'          => $transaction_id,
//                     'amount'       => $withdraw->amount,
//                     'balance'      => $member->balance,
//                     'method'       => $withdraw->method ?? 'Wallet',
//                     'type'         => 'debit',
//                 ]);

//                 Toastr::success('Withdraw approved and history recorded.');
//             } 
            
//             elseif ($new_status === 'rejected' && $old_status !== 'rejected') {
//                 $member->increment('balance', $withdraw->amount);
//                 Toastr::error('Withdraw rejected and amount refunded.');
//             }

//             DB::commit();
//             return redirect()->back();

//         } catch (\Exception $e) {
//             DB::rollBack();
//             Toastr::error('Error: ' . $e->getMessage());
//             return redirect()->back();
//         }
//     }


    public function status(Request $request)
    {
        $withdraw = WalletWithdraw::find($request->hidden_id);
        $company = Company::first();

        if (!$withdraw) {
            Toastr::error('Withdraw not found!');
            return redirect()->back();
        }

        $old_status = $withdraw->status;
        $new_status = $request->status;
        $member = Member::find($withdraw->member_id);

        if (!$member) {
            Toastr::error('Member not found!');
            return redirect()->back();
        }

        if (!$company) {
            Toastr::error('Company settings not found!');
            return redirect()->back();
        }

        try {
            DB::beginTransaction();

            $withdraw->status = $new_status;
            $withdraw->save();

            if ($new_status === 'approved' && $old_status !== 'approved') {
                // কোম্পানির মেইন ব্যালেন্স থেকে টাকা কমবে
                $company->decrement('balance', $withdraw->amount);
                
                $transaction_id = 'WTH' . now()->format('ymdHis') . strtoupper(Str::random(3));

                // মেম্বারের হিস্ট্রি (টাকা অলরেডি উইথড্র রিকোয়েস্টের সময় কাটা হয়েছে ধরে নিচ্ছি)
                CustomerPayHistory::create([
                    'member_id'    => $member->id,
                    'payment_name' => 'Withdraw Approved',
                    'tnx'          => $transaction_id,
                    'amount'       => $withdraw->amount,
                    'balance'      => $member->balance, 
                    'method'       => $withdraw->method ?? 'Wallet',
                    'type'         => 'debit',
                ]);

                AdminPayHistory::create([
                    'member_id'    => $member->id,
                    'payment_name' => 'Withdraw Approved for ' . $member->name,
                    'tnx'          => $transaction_id,
                    'amount'       => $withdraw->amount,
                    'balance'      => $member->balance,
                    'method'       => $withdraw->method ?? 'Wallet',
                    'type'         => 'debit',
                ]);
            } 
            elseif ($new_status === 'rejected' && $old_status !== 'rejected') {
                // রিজেক্ট হলে মেম্বারের ব্যালেন্স ফেরত দেওয়া (রিফান্ড)
                $member->increment('balance', $withdraw->amount);
            }

            DB::commit();

            // --- নোটিফিকেশন লজিক ---
            try {
                $title = "Withdraw Update";
                if ($new_status === 'approved') {
                    $body = "Congratulations! Your withdraw of {$withdraw->amount} has been approved.";
                } elseif ($new_status === 'rejected') {
                    $body = "Your withdraw request of {$withdraw->amount} was rejected and the amount has been refunded.";
                } else {
                    $body = "Your withdraw status is now " . ucfirst($new_status);
                }

                $this->sendFcmNotification($withdraw->member_id, $title, $body, [
                    'withdraw_id' => (string)$withdraw->id,
                    'status' => (string)$new_status
                ]);
            } catch (\Exception $e) {
                \Log::error("Withdraw FCM Error: " . $e->getMessage());
            }

            Toastr::success('Withdraw marked as ' . $new_status . ' successfully');
            return redirect()->back();

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Withdraw Status Error: " . $e->getMessage());
            Toastr::error('Something went wrong. Please try again.');
            return redirect()->back();
        }
    }



    private function sendFcmNotification($memberId, $title, $body, $data = [])
    {
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
                        "notification" => ["title" => $title, "body" => $body],
                        "data" => empty($data) ? null : $data
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
