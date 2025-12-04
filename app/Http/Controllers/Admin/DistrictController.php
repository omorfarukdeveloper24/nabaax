<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\District;
use Image;
use Toastr;
use Str;

class DistrictController extends Controller
{
    function __construct()
    {
        // $this->middleware('permission:district-list', ['only' => ['index']]);
        // $this->middleware('permission:district-edit', ['only' => ['edit','update']]);
        // $this->middleware('permission:district-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {

        $data = District::orderBy('id', 'DESC')->get();
        $districts = District::select('district', 'shippingfee')->distinct()->get();
        return view('backEnd.district.index', compact('data', 'districts'));
    }

    public function edit($id)
    {
        $edit_data = District::find($id);
        return view('backEnd.district.edit', compact('edit_data'));
    }

    public function update(Request $request)
    {
        $this->validate($request, [
            'shippingfee' => 'required',
        ]);
        $update_data = District::find($request->id);
        $input = $request->all();
        $update_data->update($input);
        Toastr::success('Success', 'Data update successfully');
        return redirect()->route('districts.index');
    }

    public function district_charge(Request $request)
    {
        $this->validate($request, [
            'shippingfee' => 'required',
        ]);
        $update_data = District::where('district', $request->district)->update([
            'shippingfee' => $request->shippingfee
        ]);
        Toastr::success('Success', 'Data update successfully');
        return redirect()->route('districts.index');
    }
}
