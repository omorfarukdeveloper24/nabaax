<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use App\Models\PaymentChargeSetting;
use Toastr;
use Image;
use File;
use DB;

class PaymentChargeSettingController extends Controller
{
    function __construct()
    {
        $this->middleware('permission:paymentcharge-list|paymentcharge-create|paymentcharge-edit|paymentcharge-delete', ['only' => ['index','store']]);
        $this->middleware('permission:paymentcharge-create', ['only' => ['create','store']]);
        $this->middleware('permission:paymentcharge-edit', ['only' => ['edit','update']]);
        $this->middleware('permission:paymentcharge-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        $show_data = PaymentChargeSetting::orderBy('id','DESC')->get();
        return view('backEnd.paymentcharges.index',compact('show_data'));
    }
    public function create()
    {
        return view('backEnd.paymentcharges.create');
    }
    public function store(Request $request)
    {
        // $this->validate($request, [
        //     'name' => 'required',
        //     'white_logo' => 'required',
        //     'favicon' => 'required',
        //     'status' => 'required',
        // ]);
        return "Only Admin can create";
        // image with intervention 
        $image = $request->file('white_logo');
        $name =  time().'-'.$image->getClientOriginalName();
        $name = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp',$name);
        $name = strtolower(preg_replace('/\s+/', '-', $name));
        $uploadpath = 'public/uploads/paymentcharges/';
        $imageUrl = $uploadpath.$name; 
        $img=Image::make($image->getRealPath());
        $img->encode('webp', 90);
        $width = '';
        $height = '';
        $img->height() > $img->width() ? $width=null : $height=null;
        $img->resize($width, $height);
        $img->save($imageUrl);

        // dark logo
        $image2 = $request->file('dark_logo');
        $name2 =  time().'-'.$image2->getClientOriginalName();
        $name2 = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp',$name2);
        $name2 = strtolower(preg_replace('/\s+/', '-', $name2));
        $uploadpath2 = 'public/uploads/paymentcharges/';
        $image2Url = $uploadpath2.$name2; 
        $img2=Image::make($image2->getRealPath());
        $img2->encode('webp', 90);
        $width2 = '';
        $height2 = '';
        $img2->height() > $img2->width() ? $width2=null : $height2=null;
        $img2->resize($width2, $height2);
        $img2->save($image2Url);



        $input = $request->all();
        $input['white_logo'] = $imageUrl;
        $input['dark_logo'] = $image2Url;
        $input['favicon'] = $image3Url;
        PaymentChargeSetting::create($input);
        Toastr::success('Success','Data insert successfully');
        return redirect()->route('paymentcharges.index');
    }
    
    public function edit($id)
    {
        $edit_data = PaymentChargeSetting::find($id);
        return view('backEnd.paymentcharges.edit',compact('edit_data'));
    }
    
    public function update(Request $request)
    {
        $this->validate($request, [
            'min_deposit'            => 'required|numeric',
            'min_withdraw'           => 'required|numeric',
            'transfer_limit'         => 'required|numeric',
            'first_gen_bonus'        => 'required|numeric',
            'multi_gen_bonus'        => 'required|numeric',
            'partner_own_bonus'      => 'required|numeric',
            'partner_min_balance'    => 'required|numeric',
        ]);

        $update_data = PaymentChargeSetting::findOrFail($request->id);
        $input = $request->all();

        
        $input['status'] = $request->status?1:0;

        $update_data->update($input);

        Toastr::success('Success', 'Payment charge data updated successfully');
        return redirect()->route('paymentcharges.index');
    }
 
    public function inactive(Request $request)
    {
        $inactive = PaymentChargeSetting::find($request->hidden_id);
        $inactive->status = 0;
        $inactive->save();
        Toastr::success('Success','Data inactive successfully');
        return redirect()->back();
    }
    public function active(Request $request)
    {
        $active = PaymentChargeSetting::find($request->hidden_id);
        $active->status = 1;
        $active->save();
        Toastr::success('Success','Data active successfully');
        return redirect()->back();
    }
    public function destroy(Request $request)
    {
        $delete_data = PaymentChargeSetting::find($request->hidden_id);
        return "You can not delete this data";
        File::delete($delete_data->image);
        $delete_data->delete();
        Toastr::success('Success','Data delete successfully');
        return redirect()->back();
    }


}
