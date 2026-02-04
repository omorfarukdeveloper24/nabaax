<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VerifyMemberController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->verified; // pending, approved, inactive
        
        $data = Member::query()
            ->when($status !== null, function ($query) use ($status) {
                return $query->where('verified', $status);
            })
            ->latest()
            ->paginate(30);

        return view('backEnd.member.index', compact('data', 'status'));
    }

    // মেম্বার প্রোফাইল ভিউ
    public function show($id)
    {
        $details = Member::findOrFail($id);
        return view('backEnd.member.show', compact('details'));
    }

    // মেম্বার স্ট্যাটাস পরিবর্তন (Reject/Approve)
    public function status(Request $request)
    {
        $member = Member::findOrFail($request->hidden_id);
        $member->status = $request->status;
        $member->save();

        Toastr::success('Member status updated to ' . $request->status);
        return redirect()->back();
    }

    // মেম্বার ডিলিট
    public function destroy($id)
    {
        $member = Member::findOrFail($id);
        // ইমেজ থাকলে ডিলিট করার কোড এখানে দিতে পারেন
        $member->delete();

        Toastr::success('Member deleted successfully');
        return redirect()->back();
    }

    
}
