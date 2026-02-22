<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Member;
use App\Models\Deposit;
use App\Models\WalletWithdraw;
use App\Models\BalanceTransfer;
use App\Models\PaymentChargeSetting;
use App\Models\CustomerPayHistory;
use App\Models\AdminPayHistory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class PaymentServiceController extends Controller
{
     function __construct()
    {
        $this->middleware("auth.jwt", [
            
        ]);
    }
    
    public function dpstore(Request $request)
    {
        $member = Auth::guard('member')->user();
        $dipositlimit = PaymentChargeSetting::first()->min_deposit;
        
        if (!$request->amount) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Please enter your amount.'
            ]);
        }
    
        if ($request->amount < $dipositlimit) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Minimum ' . $dipositlimit . ' amount required.'
            ]);
        }
    
        if (!$request->method) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Please select your method.'
            ]);
        }
    
        if (!$request->sender_number) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Please enter your sender number.'
            ]);
        }
    
        if (Deposit::where('tnx_id', $request->tnx_id)->exists()) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'This transaction ID is already taken.'
            ]);
        }
        
        $deposit = Deposit::create([
            'member_id'     => $member->id,
            'amount'        => $request->amount,
            'method'        => $request->method,
            'sender_number' => $request->sender_number,
            'tnx_id'        => $request->tnx_id,
            'status'        => 'pending',
        ]);
        
        return response()->json([
            'status'  => 'success',
            'message' => 'Deposit request sent successfully. Please wait for approval.',
            'data'    => $deposit
        ]);
    }
    
    public function deposit_list()
    {
        $member = Auth::guard('member')->user();

        $deposits = Deposit::where('member_id', $member->id)
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $deposits
        ]);
    }

    public function all_payment()
    {
        $member = Auth::guard('member')->user();

        $all_history = CustomerPayHistory::where('member_id', $member->id)
            ->latest()
            ->paginate(30);

        return response()->json([
            'status' => 'success',
            'data' => $all_history,
        ]);
    }

    public function receive_payment()
    {
        $member = Auth::guard('member')->user();

        $receive_history = CustomerPayHistory::where('member_id', $member->id)
            ->where('tnx', 'LIKE', '%R') 
            ->latest()
            ->paginate(30);

        return response()->json([
            'status' => 'success',
            'data' => $receive_history,
        ]);
    }
    
    public function withdraw_store(Request $request)
    {
        $member = Auth::guard('member')->user();
        $withdrawlimit = PaymentChargeSetting::first()->min_withdraw;
        
        if (!$request->amount) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Please enter your amount.'
            ]);
        }

        if ($request->amount < $withdrawlimit) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Minimum ' . $withdrawlimit . ' amount required.'
            ]);
        }
    
        if (!$request->method) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Please select your method.'
            ]);
        }
    
        if (!$request->receiver_number) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Please enter your sender number.'
            ]);
        }
        
        if (!$request->password) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Please enter your password.'
            ]);
        }
        
        if (!Hash::check($request->password, $member->password)) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Incorrect password.'
            ]);
        }
    
       if ($member->balance >= $request->amount) {
           $member->balance = $member->balance - $request->amount;
           $member->save();

            $withdraw = WalletWithdraw::create([
                'member_id'      => $member->id,
                'amount'         => $request->amount,
                'method'         => $request->method,
                'receiver_number'=> $request->receiver_number,
                'status'         => 'pending',
            ]);
    
            
    
            return response()->json([
                'status'  => 'success',
                'message' => 'Withdraw request sent successfully. Your balance has been updated. Please wait for approval.',
                'data'    => $withdraw
            ]);
    
        } else {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Insufficient balance.'
            ]);
        }
    
    }
    
    
    public function withdraw_list()
    {
        $member = Auth::guard('member')->user();

        $withdraws = WalletWithdraw::where('member_id', $member->id)
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $withdraws
        ]);
    }

    // public function balance_transfer(Request $request)
    // {
    //     $member = Auth::guard('member')->user();
        
    //     $settings = PaymentChargeSetting::first();
    //     $transferlimit = $settings ? $settings->transfer_limit : 100;

    //     if (!$request->amount) {
    //         return response()->json(['status' => 'failed', 'message' => 'Please enter your amount.']);
    //     }
    //     if (!$request->amount || $request->amount < $transferlimit) {
    //         return response()->json(['status' => 'failed', 'message' => "Minimum $transferlimit amount required."]);
    //     }

    //     if (!$request->username) {
    //         return response()->json(['status' => 'failed', 'message' => 'Enter target username.']);
    //     }

    //     if (!Hash::check($request->password, $member->password)) {
    //         return response()->json(['status' => 'failed', 'message' => 'Incorrect password.']);
    //     }

    //     $childMember = Member::where('username', $request->username)->first();

    //     if (!$childMember || $childMember->id == $member->id) {
    //         return response()->json(['status' => 'failed', 'message' => 'Invalid target member.']);
    //     }

    //     if ($member->balance < $request->amount) {
    //         return response()->json(['status' => 'failed', 'message' => 'Insufficient balance.']);
    //     }

    //     try {
    //         DB::beginTransaction(); 

    //         $member->decrement('balance', $request->amount);
    //         $childMember->increment('balance', $request->amount);

    //         $transaction_id = 'TRX' . now()->format('ymdHis') . strtoupper(Str::random(3));

    //         BalanceTransfer::create([
    //             'sender_id'   => $member->id,
    //             'receiver_id' => $childMember->id,
    //             'amount'      => $request->amount,
    //         ]);

    //         CustomerPayHistory::create([
    //             'member_id' => $member->id,
    //             'payment_name'  => 'Balance Sent to ' . $childMember->username,
    //             'tnx'       => $transaction_id . '-S',
    //             'amount'    => $request->amount,
    //             'balance'   => $member->balance,
    //             'method'    => 'Wallet',
    //             'type'      => 'debit',
    //         ]);

    //         CustomerPayHistory::create([
    //             'member_id' => $childMember->id,
    //             'payment_name'  => 'Balance Received from ' . $member->username,
    //             'tnx'       => $transaction_id . '-R',
    //             'amount'    => $request->amount,
    //             'balance'   => $childMember->balance,
    //             'method'    => 'Wallet',
    //             'type'      => 'credit',
    //         ]);

    //         DB::commit(); 

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Balance transferred successfully!',
    //             'data' => $member
    //         ]);

    //     } catch (\Exception $e) {
    //         DB::rollBack(); 
    //         return response()->json([
    //             'status' => 'failed', 
    //             'message' => $e->getMessage()
    //         ]);
    //     }
    // }

    public function balance_transfer(Request $request)
    {
        $member = Auth::guard('member')->user();
        
        $settings = PaymentChargeSetting::first();
        $transferlimit = $settings ? $settings->transfer_limit : 100;

        if (!$request->amount || $request->amount < $transferlimit) {
            return response()->json(['status' => 'failed', 'message' => "Minimum $transferlimit amount required."]);
        }

        if (!$request->username) {
            return response()->json(['status' => 'failed', 'message' => 'Enter target username.']);
        }

        if (!Hash::check($request->password, $member->password)) {
            return response()->json(['status' => 'failed', 'message' => 'Incorrect password.']);
        }

        $childMember = Member::where('username', $request->username)->first();

        if (!$childMember || $childMember->id == $member->id) {
            return response()->json(['status' => 'failed', 'message' => 'Invalid target member.']);
        }

        if ($member->balance < $request->amount) {
            return response()->json(['status' => 'failed', 'message' => 'Insufficient balance.']);
        }

        try {
            DB::beginTransaction(); 

            $member->decrement('balance', $request->amount);
            $childMember->increment('balance', $request->amount);

            $transaction_id = 'TRX' . now()->format('ymdHis') . strtoupper(Str::random(3));

            BalanceTransfer::create([
                'sender_id'   => $member->id,
                'receiver_id' => $childMember->id,
                'amount'      => $request->amount,
            ]);

            CustomerPayHistory::create([
                'member_id' => $member->id,
                'payment_name'  => 'Balance Sent to ' . $childMember->username,
                'tnx'       => $transaction_id . '-S',
                'amount'    => $request->amount,
                'balance'   => $member->balance,
                'method'    => 'Wallet',
                'type'      => 'debit',
            ]);

            CustomerPayHistory::create([
                'member_id' => $childMember->id,
                'payment_name'  => 'Balance Received from ' . $member->username,
                'tnx'       => $transaction_id . '-R',
                'amount'    => $request->amount,
                'balance'   => $childMember->balance,
                'method'    => 'Wallet',
                'type'      => 'credit',
            ]);

            DB::commit(); 

            // --- নোটিফিকেশন লজিক শুরু ---
            try {
                // ১. প্রেরকের জন্য নোটিফিকেশন (Debit)
                $senderTitle = "Balance Sent Successfully";
                $senderBody  = "You have successfully sent {$request->amount} TK to {$childMember->username}.";
                $this->sendFcmNotification($member->id, $senderTitle, $senderBody, [
                    'type' => 'balance_transfer',
                    'action' => 'sent'
                ]);

                // ২. প্রাপকের জন্য নোটিফিকেশন (Credit)
                $receiverTitle = "Balance Received";
                $receiverBody  = "You have received {$request->amount} TK from {$member->username}.";
                $this->sendFcmNotification($childMember->id, $receiverTitle, $receiverBody, [
                    'type' => 'balance_transfer',
                    'action' => 'received'
                ]);
            } catch (\Exception $e) {
                \Log::error("Transfer FCM Error: " . $e->getMessage());
            }
            // --- নোটিফিকেশন লজিক শেষ ---

            return response()->json([
                'status' => 'success',
                'message' => 'Balance transferred successfully!',
                'data' => $member
            ]);

        } catch (\Exception $e) {
            DB::rollBack(); 
            return response()->json([
                'status' => 'failed', 
                'message' => $e->getMessage()
            ]);
        }
    }






    
    
    
    
    public function transfer_list()
    {
        $member = Auth::guard('member')->user();

        $transfers = BalanceTransfer::where('sender_id', $member->id)->with('receiver')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $transfers
        ]);
    }
    
    
    
    
}
