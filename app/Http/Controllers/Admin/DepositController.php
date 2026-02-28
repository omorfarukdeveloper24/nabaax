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
use App\Traits\NotificationTrait;
class DepositController extends Controller
{
    use NotificationTrait;
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
                $title = "Deposit ($new_status)";
                $body = "Your deposit of {$deposit->amount} has been " . ucfirst($new_status);
                
                // ১. মেম্বার আইডি (কার কাছে যাবে)
                // ২. টাইটেল (নোটিফিকেশন হেডলাইন)
                // ৩. বডি (মূল মেসেজ)
                // ৪. ডাটা অ্যারে (অ্যাপের ভেতর লজিক হ্যান্ডেল করার জন্য)
                // ৫. নোটিফিকেশন টাইপ (ডাটাবেসে সেভ করার জন্য এবং ফিল্টার করার জন্য)

                $this->sendFcmNotification(
                    $deposit->member_id,               // ১
                    $title,                            // ২
                    $body,                             // ৩
                    [                                  // ৪ (অ্যারে)
                        'deposit_id' => (string)$deposit->id,
                        'status'     => (string)$new_status,
                    ],
                    'deposit'                          // ৫ (টাইপ: উদা: deposit, withdraw, message)
                );

            } catch (\Exception $e) {
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
