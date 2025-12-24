<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Deposit;
use App\Models\Member;
use App\Models\CustomerPayHistory;
use App\Models\AdminPayHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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



    public function status(Request $request)
    {
        $deposit = Deposit::find($request->hidden_id);

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

                    $transaction_id = 'DEP' . $deposit->tnx_id;

                    CustomerPayHistory::create([
                        'member_id'    => $member->id,
                        'payment_name' => 'Deposit Approved',
                        'tnx'          => $transaction_id,
                        'amount'       => $deposit->amount,
                        'balance'      => $member->balance, 
                        'method'       => $deposit->method ?? 'Manual', 
                        'type'         => 'credit',
                    ]);

                    
                }
            }

            DB::commit();
            Toastr::success('Deposit marked as ' . $new_status . ' successfully');
            return redirect()->back();

        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error('Something went wrong. Please try again.');
            return redirect()->back();
        }
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
