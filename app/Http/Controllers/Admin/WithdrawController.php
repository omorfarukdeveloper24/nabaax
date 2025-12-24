<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WalletWithdraw;
use App\Models\Member;
use App\Models\CustomerPayHistory;
use App\Models\AdminPayHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

                $company->decrement('balance', $withdraw->amount);
                
                $transaction_id = 'WTH' . now()->format('ymdHis') . strtoupper(Str::random(3));

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

                Toastr::success('Withdraw approved and history recorded.');
            } 
            
            elseif ($new_status === 'rejected' && $old_status !== 'rejected') {
                $member->increment('balance', $withdraw->amount);
                Toastr::error('Withdraw rejected and amount refunded.');
            }

            DB::commit();
            return redirect()->back();

        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error('Error: ' . $e->getMessage());
            return redirect()->back();
        }
    }












}
