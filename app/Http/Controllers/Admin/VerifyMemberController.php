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

    // public function show($id)
    // {
    //     $details = Member::leftJoin('member_verifies', function ($join) {
    //             $join->on('members.id', '=', 'member_verifies.member_id')
    //                 ->whereRaw('member_verifies.id IN (select MAX(id) from member_verifies group by member_id)');
    //         })
    //         ->select(
    //             'members.*', 
    //             'member_verifies.nid_number as verify_nid', 
    //             'member_verifies.birth_number', 
    //             'member_verifies.type', 
    //             'member_verifies.nid_front_image', 
    //             'member_verifies.nid_back_image', 
    //             'member_verifies.birth_image', 
    //             'member_verifies.identity_image', 
    //             'member_verifies.salfy_image', 
    //             'member_verifies.passport_image', 
    //             'member_verifies.driving_front_image', 
    //             'member_verifies.driving_back_image'
    //         )
    //         ->where('members.id', $id)
    //         ->firstOrFail();

    //     return view('backEnd.member.show', compact('details'));
    // }

    public function show($id)
    {
        $details = Member::leftJoin('member_verifies', function ($join) {
                $join->on('members.id', '=', 'member_verifies.member_id')
                    ->whereRaw('member_verifies.id IN (select MAX(id) from member_verifies group by member_id)');
            })
            // ডিস্ট্রিক্ট টেবিলের সাথে জয়েন
            ->leftJoin('districts', 'members.district', '=', 'districts.id')
            // উপজেলা টেবিলের সাথে জয়েন
            ->leftJoin('upazilas', 'members.upazila', '=', 'upazilas.id')
            ->select(
                'members.*', 
                'districts.name as district_name', // ডিস্ট্রিক্টের নাম
                'upazilas.name as upazila_name',   // উপজেলার নাম
                'member_verifies.nid_number as verify_nid', 
                'member_verifies.birth_number', 
                'member_verifies.type', 
                'member_verifies.nid_front_image', 
                'member_verifies.nid_back_image', 
                'member_verifies.birth_image', 
                'member_verifies.identity_image', 
                'member_verifies.salfy_image', 
                'member_verifies.passport_image', 
                'member_verifies.driving_front_image', 
                'member_verifies.driving_back_image'
            )
            ->where('members.id', $id)
            ->firstOrFail();

        return view('backEnd.member.show', compact('details'));
    }

    // public function status(Request $request)
    // {
    //     $member = Member::findOrFail($request->hidden_id);
        
    //     // বাটন থেকে আসা ভ্যালু সেট করা
    //     $member->verified = $request->verified;
    //     $member->submit   = $request->submit;
        
    //     $member->save();

    //     $status_msg = "Updated";
    //     if($request->verified == 1) $status_msg = "Verified Successfully";
    //     if($request->verified == 'NULL') $status_msg = "Rejected";
    //     if($request->verified == 3) $status_msg = "Blocked";

    //     Toastr::success('Member status ' . $status_msg);
    //     return redirect()->back();
    // }

    public function status(Request $request)
    {
        $member = Member::findOrFail($request->hidden_id);
        
        // রিজেক্ট করা হলে (যদি বাটন থেকে verified এর মান ০ বা খালি আসে)
        if ($request->verified == '0' || $request->verified == '') {
            $member->verified = null; // ডেটাবেজে NULL সেভ হবে
            $member->submit   = 0;    // সাবমিট ০ হবে যাতে সে আবার অ্যাপ্লাই করতে পারে
            $status_msg = "Rejected";
        } 
        else {
            // ভেরিফাই (১) অথবা ব্লক (৩) এর জন্য
            $member->verified = $request->verified;
            $member->submit   = $request->submit;

            if($request->verified == 1) {
                $status_msg = "Verified Successfully";
            } elseif($request->verified == 3) {
                $status_msg = "Blocked";
            } else {
                $status_msg = "Updated";
            }
        }
        
        $member->save();

        Toastr::success('Member status ' . $status_msg);
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
