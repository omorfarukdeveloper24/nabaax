<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Member;
use App\Models\Deposit;
use App\Models\WalletWithdraw;
use App\Models\BalanceTransfer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;


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
        
        if (!$request->amount) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Please enter your amount.'
            ]);
        }
    
        if ($request->amount < 100) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Minimum 100 amount required.'
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
    
    public function withdraw_store(Request $request)
    {
        $member = Auth::guard('member')->user();
        
        if (!$request->amount) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Please enter your amount.'
            ]);
        }
    
        if ($request->amount < 100) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Minimum 100 amount required.'
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
           
            $withdraw = WalletWithdraw::create([
                'member_id'      => $member->id,
                'amount'         => $request->amount,
                'method'         => $request->method,
                'receiver_number'=> $request->receiver_number,
                'status'         => 'pending',
            ]);
    
            $member->balance = $member->balance - $request->amount;
            $member->save();
    
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
    
    public function balance_transfer(Request $request){
        $member = Auth::guard('member')->user();
       
        if (!$request->amount) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Please enter your amount.'
            ]);
        }
    
        if ($request->amount < 100) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Minimum 100 amount required.'
            ]);
        }
    
        if (!$request->username) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Enter the username of the account you want to send money to.'
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
        
        
        $username = $request->username;
        $childMember = Member::where('username', $username)->first();
        if (!$childMember) {
            return response()->json([
                'status' => 'failed',
                'message' => 'No member found this username.'
            ]);
        }
        if ($childMember->id == $member->id) {
            return response()->json([
                'status' => 'failed',
                'message' => "Don't use your own username."
            ]);
        }
        
        if ($member->balance < $request->amount) {
            return response()->json(['status' => 'failed', 'message' => 'Insufficient balance.']);
        }
    
        $member->decrement('balance', $request->amount);
        $childMember->increment('balance', $request->amount);
    
        
        BalanceTransfer::create([
            'sender_id'   => $member->id,
            'receiver_id' => $childMember->id,
            'amount'      => $request->amount,
        ]);
    
        return response()->json([
            'status' => 'success',
            'message' => 'Balance transferred successfully!',
            'data' => $member
        ]);
        
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
