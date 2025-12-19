<?php

namespace App\Http\Controllers\Frontend;

use shurjopayv2\ShurjopayLaravelPackage8\Http\Controllers\ShurjopayController; 
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Session;
use Gloudemans\Shoppingcart\Facades\Cart;
use App\Models\School;
use App\Models\Product;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\CampaignPro;
use App\Models\Faq;
use App\Models\Seller;
use Cache;
use DB;
use Log;

class FrontendController extends Controller
{
    public function index(){
       

        return view('frontEnd.layouts.pages.index');
    }

    public function admob(){
        return view('frontEnd.layouts.pages.admob');
    }

    public function student_add() {
        return view('frontEnd.layouts.pages.addstudent');
    }

    public function dashboards(Request $request)
    {
        $show_data = School::orderBy('id','DESC')->get();
        return view('frontEnd.layouts.school.dashboard',compact('show_data'));
    }
    
    public function delete_account_inc()
    {
        // return 'ok';
        return view('frontEnd.layouts.pages.delete');
    }
    
    
    public function privary_policy()
    {
        return view('frontEnd.layouts.pages.policy');
    }
    
    public function childsafety_standards()
    {
        return view('frontEnd.layouts.pages.childsafety');
    }
   
}
