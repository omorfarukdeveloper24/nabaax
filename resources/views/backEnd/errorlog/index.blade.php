@extends('backEnd.layouts.master')
@section('title','Error Log Manage')
@section('css')
<link href="{{asset('/public/backEnd/')}}/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css" rel="stylesheet" type="text/css" />
<link href="{{asset('/public/backEnd/')}}/assets/libs/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css" rel="stylesheet" type="text/css" />
<style>
    .badge-critical { background-color: #dc3545; color: #fff; }
    .badge-open     { background-color: #fd7e14; color: #fff; }
    .badge-retrying { background-color: #0dcaf0; color: #000; }
    .badge-resolved { background-color: #198754; color: #fff; }
    .stat-card      { border-radius: 10px; padding: 20px; color: #fff; }
    .trace-box      { background: #1e1e1e; color: #d4d4d4; border-radius: 8px; padding: 12px; font-size: 12px; max-height: 200px; overflow-y: auto; font-family: monospace; }
    .filter-bar     { background: #f8f9fa; border-radius: 10px; padding: 16px; margin-bottom: 20px; }
    .action-btn     { border-radius: 6px; font-size: 12px; padding: 4px 10px; }
</style>
@endsection

@section('content')
<div class="container-fluid">

    {{-- Page Title --}}
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">Error Log Monitor</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Error Logs</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card" style="background: linear-gradient(135deg,#6c757d,#495057)">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="mb-1" style="font-size:13px;opacity:.8">Total Errors</p>
                        <h3 class="mb-0 fw-bold">{{ $stats['total'] }}</h3>
                    </div>
                    <i data-feather="layers" style="width:40px;height:40px;opacity:.6"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card" style="background: linear-gradient(135deg,#dc3545,#b02a37)">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="mb-1" style="font-size:13px;opacity:.8">Critical</p>
                        <h3 class="mb-0 fw-bold">{{ $stats['critical'] }}</h3>
                    </div>
                    <i data-feather="alert-octagon" style="width:40px;height:40px;opacity:.6"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card" style="background: linear-gradient(135deg,#fd7e14,#c96b0e)">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="mb-1" style="font-size:13px;opacity:.8">Open</p>
                        <h3 class="mb-0 fw-bold">{{ $stats['open'] }}</h3>
                    </div>
                    <i data-feather="alert-circle" style="width:40px;height:40px;opacity:.6"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-card" style="background: linear-gradient(135deg,#198754,#13653f)">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="mb-1" style="font-size:13px;opacity:.8">Resolved</p>
                        <h3 class="mb-0 fw-bold">{{ $stats['resolved'] }}</h3>
                    </div>
                    <i data-feather="check-circle" style="width:40px;height:40px;opacity:.6"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i data-feather="check-circle" class="me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i data-feather="x-circle" class="me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Filter Bar --}}
    <div class="filter-bar">
        <form method="GET" action="{{ route('errorloghistory.index') }}">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold" style="font-size:13px">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        <option value="open"     {{ request('status')=='open'     ? 'selected' : '' }}>Open</option>
                        <option value="critical" {{ request('status')=='critical' ? 'selected' : '' }}>Critical</option>
                        <option value="retrying" {{ request('status')=='retrying' ? 'selected' : '' }}>Retrying</option>
                        <option value="resolved" {{ request('status')=='resolved' ? 'selected' : '' }}>Resolved</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold" style="font-size:13px">Source</label>
                    <select name="source" class="form-select form-select-sm">
                        <option value="">All Sources</option>
                        @foreach($sources as $source)
                            <option value="{{ $source }}" {{ request('source')==$source ? 'selected' : '' }}>
                                {{ $source }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold" style="font-size:13px">Date</label>
                    <input type="date" name="date" class="form-control form-control-sm" value="{{ request('date') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold" style="font-size:13px">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Error message..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-50">
                        <i data-feather="search" style="width:14px;height:14px"></i> Filter
                    </button>
                    <a href="{{ route('errorloghistory.index') }}" class="btn btn-secondary btn-sm w-50">Reset</a>
                </div>
            </div>
        </form>
    </div>

    {{-- Main Table --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Error Logs</h5>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-success" onclick="resolveSelected()">
                    <i data-feather="check" style="width:14px;height:14px"></i> Bulk Resolve
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                    <i data-feather="refresh-cw" style="width:14px;height:14px"></i> Refresh
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="errorTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th>Source</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Retry</th>
                            <th>Time</th>
                            <th style="width:180px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($errors as $error)
                        <tr class="{{ $error->status === 'critical' ? 'table-danger' : '' }}">
                            <td>
                                <input type="checkbox" class="form-check-input error-check" value="{{ $error->id }}">
                            </td>
                            <td>
                                <span class="fw-semibold" style="font-size:13px">{{ $error->source }}</span>
                                <br>
                                <small class="text-muted">{{ $error->type }}</small>
                            </td>
                            <td style="max-width:250px">
                                <span style="font-size:13px" class="d-block text-truncate" title="{{ $error->message }}">
                                    {{ Str::limit($error->message, 80) }}
                                </span>
                                @if($error->context)
                                    <small class="text-muted">
                                        @foreach(array_slice((array)$error->context, 0, 2) as $key => $val)
                                            <span class="badge bg-light text-dark">{{ $key }}: {{ $val }}</span>
                                        @endforeach
                                    </small>
                                @endif
                            </td>
                            <td>
                                @php
                                    $badgeMap = [
                                        'critical' => 'badge-critical',
                                        'open'     => 'badge-open',
                                        'retrying' => 'badge-retrying',
                                        'resolved' => 'badge-resolved',
                                    ];
                                @endphp
                                <span class="badge {{ $badgeMap[$error->status] ?? 'bg-secondary' }}">
                                    {{ ucfirst($error->status) }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-semibold">{{ $error->retry_count }}/{{ $error->max_retries }}</span>
                                @if($error->retry_count >= $error->max_retries)
                                    <br><small class="text-danger">Max reached</small>
                                @endif
                            </td>
                            <td style="font-size:12px;white-space:nowrap">
                                {{ $error->created_at->diffForHumans() }}
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    {{-- Details Button --}}
                                    <button class="btn btn-sm btn-outline-info action-btn"
                                        onclick="showDetails({{ $error->id }})"
                                        data-id="{{ $error->id }}">
                                        <i data-feather="eye" style="width:12px;height:12px"></i>
                                    </button>

                                    {{-- Retry Button --}}
                                    @if($error->status !== 'resolved')
                                        <form action="{{ route('errorloghistory.retry', $error->id) }}" method="POST" style="display:inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-primary action-btn"
                                                onclick="return confirm('এই Job retry করবেন?')"
                                                title="Retry">
                                                <i data-feather="rotate-cw" style="width:12px;height:12px"></i>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Resolve Button --}}
                                    @if($error->status !== 'resolved')
                                        <button class="btn btn-sm btn-outline-success action-btn"
                                            onclick="openResolveModal({{ $error->id }})"
                                            title="Resolve">
                                            <i data-feather="check" style="width:12px;height:12px"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        {{-- Detail Row (hidden) --}}
                        <tr id="detail-{{ $error->id }}" style="display:none;background:#f8f9fa">
                            <td colspan="7" class="p-3">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <h6 class="fw-semibold mb-2">Context</h6>
                                        @if($error->context)
                                            @foreach($error->context as $key => $val)
                                                <div class="d-flex justify-content-between border-bottom py-1">
                                                    <small class="text-muted">{{ $key }}</small>
                                                    <small class="fw-semibold">{{ $val }}</small>
                                                </div>
                                            @endforeach
                                        @else
                                            <small class="text-muted">No context</small>
                                        @endif

                                        @if($error->resolved_at)
                                            <div class="mt-2 p-2 bg-success bg-opacity-10 rounded">
                                                <small class="text-success">
                                                    ✅ Resolved {{ $error->resolved_at->diffForHumans() }}
                                                </small>
                                                @if($error->admin_note)
                                                    <p class="mb-0 mt-1" style="font-size:12px">{{ $error->admin_note }}</p>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    <div class="col-md-8">
                                        <h6 class="fw-semibold mb-2">Error Trace</h6>
                                        @if($error->trace)
                                            <div class="trace-box">{{ $error->trace }}</div>
                                        @else
                                            <small class="text-muted">No trace available</small>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i data-feather="check-circle" style="width:48px;height:48px;color:#198754"></i>
                                <p class="mt-2 text-muted">কোনো error নেই! সব ঠিক আছে।</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($errors->hasPages())
        <div class="card-footer">
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    মোট {{ $errors->total() }} টি error | পেজ {{ $errors->currentPage() }}/{{ $errors->lastPage() }}
                </small>
                {{ $errors->links('pagination::bootstrap-5') }}
            </div>
        </div>
        @endif
    </div>

</div>

{{-- Resolve Modal --}}
<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="resolveForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Error Resolve করুন</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Admin Note (optional)</label>
                        <textarea name="admin_note" class="form-control" rows="4"
                            placeholder="কী কারণে হয়েছিল এবং কীভাবে fix করলেন..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i data-feather="check" style="width:14px;height:14px"></i> Resolve করুন
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Bulk Resolve Form --}}
<form id="bulkResolveForm" method="POST" action="{{ route('errorloghistory.bulk-resolve') }}" style="display:none">
    @csrf
    <div id="bulkIds"></div>
</form>

@endsection

@section('script')
<script src="{{asset('/public/backEnd/')}}/assets/libs/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="{{asset('/public/backEnd/')}}/assets/libs/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
<script src="{{asset('/public/backEnd/')}}/assets/libs/datatables.net-responsive/js/dataTables.responsive.min.js"></script>
<script src="{{asset('/public/backEnd/')}}/assets/libs/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js"></script>

<script>
// Detail row toggle
function showDetails(id) {
    const row = document.getElementById('detail-' + id);
    if (row.style.display === 'none') {
        row.style.display = 'table-row';
    } else {
        row.style.display = 'none';
    }
    if (typeof feather !== 'undefined') feather.replace();
}

// Resolve Modal
function openResolveModal(id) {
    document.getElementById('resolveForm').action = '/nb65vartex/errorloghistory/' + id + '/resolve';
    var modal = new bootstrap.Modal(document.getElementById('resolveModal'));
    modal.show();
}

// Select All checkbox
document.getElementById('selectAll').addEventListener('change', function () {
    document.querySelectorAll('.error-check').forEach(cb => cb.checked = this.checked);
});

// Bulk Resolve
function resolveSelected() {
    const checked = document.querySelectorAll('.error-check:checked');
    if (checked.length === 0) {
        alert('কোনো error select করা হয়নি।');
        return;
    }
    if (!confirm(checked.length + 'টি error resolve করবেন?')) return;

    const container = document.getElementById('bulkIds');
    container.innerHTML = '';
    checked.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = cb.value;
        container.appendChild(input);
    });
    document.getElementById('bulkResolveForm').submit();
}

// Auto refresh stats every 60 seconds
setInterval(function () {
    fetch('{{ route('errorloghistory.stats') }}')
        .then(r => r.json())
        .then(data => {
            console.log('Stats refreshed:', data);
        });
}, 60000);

// Feather icons init
if (typeof feather !== 'undefined') feather.replace();
</script>
@endsection