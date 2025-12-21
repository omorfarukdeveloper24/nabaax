<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AdminPayHistory;

class AdminPayHistoryController extends Controller
{
    public function index(Request $request)
    {
        $data = AdminPayHistory::orderBy('id', 'DESC')->get();
        return view('backEnd.paymenthistory.index', compact('data'));
    }
}
