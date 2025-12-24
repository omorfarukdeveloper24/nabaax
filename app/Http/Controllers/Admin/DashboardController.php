<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Customer;
use App\Models\Company;
use App\Models\WalletWithdraw;
use App\Models\Deposit;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use DB;


class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->except(['locked','unlocked']);
    }
    public function dashboard(){
        $users = User::get();

        $total_balance = Member::sum('balance');
        $total_cash = Company::value('balance') ?? 0;
        $total_deposit = Deposit::where('status','approved')->sum('amount');
        $total_withdraw = WalletWithdraw::where('status','approved')->sum('amount');
        return view('backEnd.nb65vartex.dashboard', compact('users','total_balance','total_cash','total_deposit','total_withdraw'));
        
    }
    public function changepassword()
    {
        return view('backEnd.nb65vartex.changepassword');
    }
    public function newpassword(Request $request)
    {
        $this->validate($request, [
            'old_password' => 'required',
            'new_password' => 'required',
            'confirm_password' => 'required_with:new_password|same:new_password|'
        ]);

        $user = User::find(Auth::id());
        $hashPass = $user->password;

        if (Hash::check($request->old_password, $hashPass)) {
            $user->fill([
                'password' => Hash::make($request->new_password)
            ])->save();

            Toastr::success('Success', 'Password changed successfully!');
            return redirect()->route('dashboard');
        } else {
            Toastr::error('Failed', 'Old password not match!');
            return back();
        }
    }
    public function locked()
    {
        // only if user is logged in

        Session::put('locked', true);
        return view('backEnd.auth.locked');
        return redirect()->route('login');
    }

    public function unlocked(Request $request)
    {
        if (!Auth::check())
            return redirect()->route('login');
        $password = $request->password;
        if (Hash::check($password, Auth::user()->password)) {
            Session::forget('locked');
            Toastr::success('Success', 'You are logged in successfully!');
            return redirect()->route('dashboard');
        }
        Toastr::error('Failed', 'Your password not match!');
        return back();
    }
}
