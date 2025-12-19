<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WalletWithdraw;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use DB;

class WithdrawController extends Controller
{
    /**
     * Show withdraw list with optional status filter
     */
    public function index(Request $request)
    {
        $status = $request->status ?? 'pending';
        $data = WalletWithdraw::when($request->status, function ($query) use ($request) {
                    $query->where('status', $request->status);
                })
                ->latest()
                ->paginate(50);
        // return $data;

        return view('backEnd.withdraw.index', compact('data','status'));
    }

    
    public function status(Request $request)
    {
        
        $withdraw = WalletWithdraw::find($request->hidden_id);

        if (!$withdraw) {
            Toastr::error('withdraw not found!');
            return redirect()->back();
        }
        
        

        $old_status = $withdraw->status;
        $withdraw->status = $request->status;
        $withdraw->save();

        
        if ($request->status === 'approved' && $old_status !== 'approved') {

            $member = Member::find($withdraw->member_id);

            if ($member) {
                $member->balance += $withdraw->amount;
                $member->save();
            }
        }

        Toastr::success('withdraw marked as ' . $request->status . ' successfully');
        return redirect()->back();
    }
}
