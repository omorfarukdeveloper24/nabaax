@extends('backEnd.layouts.master')
@section('title', 'Member Profile - ' . $details->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="page-title fw-bold">Member Management</h4>
                <div class="page-title-right">
                    <a href="{{ route('verifymember.index') }}" class="btn btn-outline-secondary btn-rounded shadow-sm">
                        <i class="fe-arrow-left me-1"></i> Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-3 d-flex flex-wrap justify-content-center align-items-center gap-3">
                    <span class="text-muted fw-medium me-2"><i class="fe-settings me-1"></i> Quick Actions:</span>
                    
                    <form action="{{ route('verifymember.status') }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="hidden_id" value="{{ $details->id }}">
                        <input type="hidden" name="verified" value="1">
                        <input type="hidden" name="submit" value="1">
                        <button type="submit" class="btn btn-success rounded-pill px-4 shadow-sm hover-lift">
                            <i class="fe-check-circle me-1"></i> Verify Member
                        </button>
                    </form>

                    <form action="{{ route('verifymember.status') }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="hidden_id" value="{{ $details->id }}">
                        <input type="hidden" name="verified" value="0">
                        <input type="hidden" name="submit" value="0">
                        <button type="submit" class="btn btn-warning text-white rounded-pill px-4 shadow-sm hover-lift">
                            <i class="fe-x-circle me-1"></i> Reject Member
                        </button>
                    </form>

                    <form action="{{ route('verifymember.status') }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="hidden_id" value="{{ $details->id }}">
                        <input type="hidden" name="verified" value="3">
                        <input type="hidden" name="submit" value="1">
                        <button type="submit" class="btn btn-danger rounded-pill px-4 shadow-sm hover-lift">
                            <i class="fe-slash me-1"></i> Block Member
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4 col-lg-5">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-4 profile-main-card">
                <div class="profile-header-gradient" style="height: 120px; background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);"></div>
                <div class="card-body pt-0 text-center">
                    <div class="mt-n5 position-relative d-inline-block">
                        <img src="{{ asset($details->image ?? 'public/backEnd/assets/images/users/user-1.jpg') }}" 
                             class="rounded-circle border-4 border-white shadow-lg avatar-xxl" 
                             style="width: 130px; height: 130px; object-fit: cover; margin-top: -65px;">
                        @if($details->verified == 1)
                            <span class="position-absolute bottom-0 end-0 bg-success border border-2 border-white rounded-circle p-1" title="Verified Member">
                                <i class="fe-check text-white font-12"></i>
                            </span>
                        @endif
                    </div>

                    <h3 class="mt-3 mb-1 fw-bold">{{ $details->name }}</h3>
                    <p class="text-primary fw-medium mb-3">@ {{$details->username}}</p>

                    <div class="d-flex justify-content-center gap-2 mb-4">
                        @if($details->verified == 1)
                            <span class="badge bg-soft-success text-success px-3 py-2 rounded-pill font-13"><i class="fe-shield me-1"></i>Verified</span>
                        @elseif($details->verified == 3)
                            <span class="badge bg-soft-danger text-danger px-3 py-2 rounded-pill font-13"><i class="fe-slash me-1"></i>Blocked</span>
                        @else
                            <span class="badge bg-soft-warning text-warning px-3 py-2 rounded-pill font-13"><i class="fe-clock me-1"></i>Pending Review</span>
                        @endif
                    </div>

                    <div class="text-start bg-light p-3 rounded-3 mb-2">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fe-phone icon-stack text-info me-3"></i>
                            <div>
                                <small class="text-muted d-block">Phone Number</small>
                                <span class="fw-semibold text-dark">{{ $details->phone }}</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mb-3">
                            <i class="fe-mail icon-stack text-primary me-3"></i>
                            <div>
                                <small class="text-muted d-block">Email Address</small>
                                <span class="fw-semibold text-dark" style="word-break: break-all;">{{ $details->email }}</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="fe-calendar icon-stack text-success me-3"></i>
                            <div>
                                <small class="text-muted d-block">Member Since</small>
                                <span class="fw-semibold text-dark">{{ $details->created_at->format('d M, Y') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8 col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <ul class="nav nav-pills nav-justified bg-light p-1 rounded-pill mb-4 custom-nav" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active rounded-pill py-2" data-bs-toggle="tab" href="#personal" role="tab">
                                <i class="fe-user me-1"></i> Personal
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link rounded-pill py-2" data-bs-toggle="tab" href="#address" role="tab">
                                <i class="fe-map-pin me-1"></i> Address
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link rounded-pill py-2" data-bs-toggle="tab" href="#referral" role="tab">
                                <i class="fe-wallet me-1"></i> Wallet
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link rounded-pill py-2" data-bs-toggle="tab" href="#verify-docs" role="tab">
                                <i class="fe-file-text me-1"></i> Documents
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content pt-2">
                        <div class="tab-pane fade show active" id="personal" role="tabpanel">
                            <h5 class="header-title mb-4 text-primary">Personal Details</h5>
                            <div class="row g-4">
                                @php 
                                    $personalInfo = [
                                        'NID Number' => $details->nid_number ?? 'Not Provided',
                                        'Blood Group' => $details->blood ?? 'N/A',
                                        'Gender' => $details->gender,
                                        'Profession' => $details->profession,
                                        'Monthly Income' => $details->monthlyincome,
                                        'Marital Status' => $details->married
                                    ];
                                @endphp
                                @foreach($personalInfo as $label => $value)
                                <div class="col-md-6 col-sm-6 border-bottom pb-2">
                                    <label class="text-muted small fw-bold text-uppercase mb-1 d-block">{{ $label }}</label>
                                    <p class="h6 mb-0 text-dark">{{ $value }}</p>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="tab-pane fade" id="address" role="tabpanel">
                            <h5 class="header-title mb-4 text-primary">Location Information</h5>
                            <div class="row g-4">
                                <div class="col-md-6 border-bottom pb-2">
                                    <label class="text-muted small fw-bold text-uppercase mb-1 d-block">District</label>
                                    <p class="h6 mb-0">{{ $details->district }}</p>
                                </div>
                                <div class="col-md-6 border-bottom pb-2">
                                    <label class="text-muted small fw-bold text-uppercase mb-1 d-block">Upazila</label>
                                    <p class="h6 mb-0">{{ $details->upazila }}</p>
                                </div>
                                <div class="col-md-6 border-bottom pb-2">
                                    <label class="text-muted small fw-bold text-uppercase mb-1 d-block">Post Code</label>
                                    <p class="h6 mb-0">{{ $details->post_code }}</p>
                                </div>
                                <div class="col-md-12 border-bottom pb-2">
                                    <label class="text-muted small fw-bold text-uppercase mb-1 d-block">Full Address</label>
                                    <p class="h6 mb-0">{{ $details->address }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="referral" role="tabpanel">
                            <h5 class="header-title mb-4 text-primary">Financial Summary</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="card border shadow-none rounded-3 text-center p-3">
                                        <h3 class="text-success fw-bold">{{ $details->balance }}</h3>
                                        <p class="text-muted mb-0">Current BDT</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card border shadow-none rounded-3 text-center p-3">
                                        <h3 class="text-primary fw-bold">{{ $details->referrer_id }}</h3>
                                        <p class="text-muted mb-0">Referrer ID</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card border shadow-none rounded-3 text-center p-3">
                                        <h3 class="text-info fw-bold">{{ $details->referrer_code }}</h3>
                                        <p class="text-muted mb-0">Referrer Code</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="verify-docs" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="header-title text-primary mb-0">Verification Documents</h5>
                                <span class="badge bg-info p-2 px-3 rounded-3">{{ strtoupper($details->type ?? 'None') }} ID Submitted</span>
                            </div>
                            
                            <div class="alert bg-soft-secondary border-0 mb-4 p-2 px-3">
                                <small class="text-dark fw-bold">ID/NID Number: </small>
                                <span class="text-primary fw-bold">{{ $details->verify_nid ?? ($details->birth_number ?? 'Not Available') }}</span>
                            </div>

                            <div class="row">
                                @php
                                    $docs = [
                                        'NID Front' => $details->nid_front_image,
                                        'NID Back' => $details->nid_back_image,
                                        'Identity' => $details->identity_image,
                                        'Birth Reg' => $details->birth_image,
                                        'Selfie' => $details->salfy_image,
                                        'Passport' => $details->passport_image,
                                        'Driving Front' => $details->driving_front_image,
                                        'Driving Back' => $details->driving_back_image
                                    ];
                                @endphp

                                @foreach($docs as $name => $url)
                                    @if($url)
                                    <div class="col-md-4 mb-4">
                                        <div class="document-card shadow-sm border rounded-3 overflow-hidden">
                                            <div class="p-2 bg-light border-bottom text-center">
                                                <small class="fw-bold text-uppercase">{{ $name }}</small>
                                            </div>
                                            <a href="{{ $url }}" target="_blank" class="image-popup">
                                                <img src="{{ $url }}" class="img-fluid" style="height: 160px; width: 100%; object-fit: cover;">
                                            </a>
                                            <div class="p-2 text-center">
                                                <a href="{{ $url }}" download class="btn btn-sm btn-link text-primary p-0"><i class="fe-download me-1"></i>Download</a>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div> 
                </div> 
            </div> 
        </div> 
    </div>
</div>

<style>
    /* Premium Styling */
    .rounded-4 { border-radius: 1rem !important; }
    .hover-lift { transition: all 0.2s ease; }
    .hover-lift:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.1) !important; }
    
    .icon-stack {
        height: 35px; width: 35px; background: rgba(0,0,0,0.05);
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 8px; font-size: 16px;
    }
    
    .custom-nav .nav-link { color: #6c757d; font-weight: 500; }
    .custom-nav .nav-link.active { 
        background-color: #fff !important; 
        color: #3bafda !important; 
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    }
    
    .document-card { transition: all 0.3s ease; }
    .document-card:hover { transform: scale(1.03); border-color: #3bafda !important; }
    
    .bg-soft-success { background: rgba(24, 210, 110, 0.15); }
    .bg-soft-warning { background: rgba(246, 187, 66, 0.15); }
    .bg-soft-danger { background: rgba(255, 91, 91, 0.15); }
    .bg-soft-secondary { background: rgba(108, 117, 125, 0.1); }
    
    .avatar-xxl { border: 5px solid #fff; }
    .header-title { font-size: 1.1rem; font-weight: 700; border-left: 4px solid #3bafda; padding-left: 10px; }
</style>
@endsection