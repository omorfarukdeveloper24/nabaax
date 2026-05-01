<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ErrorLog;
use App\Services\ErrorLogService;
use Illuminate\Http\Request;

class ErrorLogController extends Controller
{
    // Dashboard list — million data handle করতে পারবে
    public function index(Request $request)
    {
        $query = ErrorLog::query()->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by source
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        // Filter by date
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // Search by message
        if ($request->filled('search')) {
            $query->where('message', 'LIKE', '%' . $request->search . '%');
        }

        // Paginate — million data-তে safe
        $errors = $query->paginate(20)->withQueryString();

        // Stats — index-এর কারণে fast হবে
        $stats = ErrorLogService::getStats();

        // Unique sources for filter dropdown
        $sources = ErrorLog::select('source')->distinct()->pluck('source');

        return view('backEnd.errorlog.index', compact('errors', 'stats', 'sources'));
    }

    // Single error details
    public function show($id)
    {
        $error = ErrorLog::findOrFail($id);
        return view('backEnd.errorlog.show', compact('error'));
    }

    // Admin retry করবে
    public function retry($id)
    {
        $error = ErrorLog::findOrFail($id);

        if (!$error->canRetry()) {
            return back()->with('error', 'This error is already resolved.');
        }

        ErrorLogService::retry($error);

        return back()->with('success', 'Job queue-এ পাঠানো হয়েছে!');
    }

    // Admin resolve করবে
    public function resolve(Request $request, $id)
    {
        $error = ErrorLog::findOrFail($id);

        $request->validate([
            'admin_note' => 'nullable|string|max:1000',
        ]);

        ErrorLogService::resolve(
            $error,
            auth()->id(),
            $request->admin_note
        );

        return back()->with('success', 'Error resolved হয়েছে!');
    }

    // Bulk resolve
    public function bulkResolve(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'exists:error_logs,id',
        ]);

        ErrorLog::whereIn('id', $request->ids)->update([
            'status'      => 'resolved',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        return back()->with('success', count($request->ids) . 'টি error resolve হয়েছে!');
    }

    // Stats API — dashboard badge refresh-এর জন্য
    public function stats()
    {
        return response()->json(ErrorLogService::getStats());
    }
}