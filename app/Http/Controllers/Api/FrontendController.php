<?php

namespace App\Http\Controllers\Api; 

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use App\Models\GeneralSetting;
use App\Models\Banner;
use App\Models\Contact;
use Carbon\Carbon;
use Response;
use Hash;
use Auth;
use Mail;
use Str;
use Illuminate\Support\Facades\DB;

class FrontendController extends Controller
{
     function __construct()
    {
        $this->middleware('auth.jwt', ['only' => ['getDistricts']]);
    }
    
    
    public function appconfig()
    {
        $data = GeneralSetting::where('status', 1)->first();
        return response()->json(['status' => 'success', 'message' => 'Data fatch successfully', 'data' => $data]);
    }

    public function slider()
    {
        $data = Banner::where(['status' => 1, 'category_id' => 1])->select('id', 'image', 'status', 'category_id', 'link')->get();
        return response()->json(['status' => 'success', 'message' => 'Data fatch successfully', 'data' => $data]);
    }


    public function getProfessions()
    {
        $divisions = DB::table('professions')->select('id', 'title')->orderBy('title')->get();

        return response()->json([
            'status' => 'success',
            'data' => $divisions
        ]);
    }


    public function getDivisions()
    {
        $divisions = DB::table('divisions')->select('id', 'name')->orderBy('name')->get();

        return response()->json([
            'status' => 'success',
            'data' => $divisions
        ]);
    }
    

    public function getDistricts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'division_id' => 'nullable|integer|exists:divisions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid division_id',
                'errors' => $validator->errors()
            ], 422);
        }

        $divisionId = $request->input('division_id');

        $query = DB::table('districts');

        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }

        $districts = $query->orderBy('name')->get();

        return response()->json([
            'status' => 'success',
            'data' => $districts
        ]);
    }



    public function getUpazilas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'district_id' => 'nullable|integer|exists:districts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid district_id',
                'errors' => $validator->errors()
            ], 422);
        }

        $districtId = $request->input('district_id');

        $query = DB::table('upazilas');

        if ($districtId) {
            $query->where('district_id', $districtId);
        }

        $upazilas = $query->orderBy('name')->get();

        return response()->json([
            'status' => 'success',
            'data' => $upazilas
        ]);
    }
    

}
