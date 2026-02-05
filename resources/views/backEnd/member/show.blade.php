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
                   
                    
                    <form action="{{ route('verifymember.status') }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="hidden_id" value="{{ $details->id }}">
                        <input type="hidden" name="verified" value="1">
                        <input type="hidden" name="submit" value="1">
                        <button type="submit" class="btn btn-success rounded-pill px-4 shadow hover-lift border-0" style="background: linear-gradient(135deg, #1D976C 0%, #93F9B9 100%);">
                             <i class="fe-check-circle me-1"></i> Approve
                        </button>
                    </form>

                    <form action="{{ route('verifymember.status') }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="hidden_id" value="{{ $details->id }}">
                        <input type="hidden" name="verified" value="0">
                        <input type="hidden" name="submit" value="0">
                        <button type="submit" class="btn btn-warning text-white rounded-pill px-4 shadow hover-lift border-0" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <i class="fe-x-circle me-1"></i> Reject
                        </button>
                    </form>

                    <form action="{{ route('verifymember.status') }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="hidden_id" value="{{ $details->id }}">
                        <input type="hidden" name="verified" value="3">
                        <input type="hidden" name="submit" value="1">
                        <button type="submit" class="btn btn-danger rounded-pill px-4 shadow hover-lift border-0" style="background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);">
                            <i class="fe-slash me-1"></i> Block
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
                    <ul class="nav nav-pills nav-justified bg-light p-1 rounded-pill mb-4 premium-nav" role="tablist">
                        <li class="nav-item"><a class="nav-link active rounded-pill" data-bs-toggle="tab" href="#personal"><i class="fe-user me-1"></i> Personal</a></li>
                        <li class="nav-item"><a class="nav-link rounded-pill" data-bs-toggle="tab" href="#address"><i class="fe-map-pin me-1"></i> Address</a></li>
                        <li class="nav-item"><a class="nav-link rounded-pill" data-bs-toggle="tab" href="#wallet"><i class="fe-wallet me-1"></i> Wallet</a></li>
                        <li class="nav-item"><a class="nav-link rounded-pill" data-bs-toggle="tab" href="#verify-docs"><i class="fe-file-text me-1"></i> Documents</a></li>
                    </ul>

                    <div class="tab-content pt-2">
                        <div class="tab-pane fade show active" id="personal" role="tabpanel">
                            <h5 class="header-title mb-4 text-primary">Personal Details</h5>
                            <div class="row g-3">
                                @php 
                                    $personalData = [
                                        ['label' => 'Blood Group', 'value' => $details->blood ?? 'N/A', 'icon' => 'fe-droplet'],
                                        ['label' => 'Gender', 'value' => $details->gender, 'icon' => 'fe-user'],
                                        ['label' => 'Profession', 'value' => $details->profession, 'icon' => 'fe-briefcase'],
                                        ['label' => 'Monthly Income', 'value' => $details->monthlyincome, 'icon' => 'fe-trending-up'],
                                        ['label' => 'Marital Status', 'value' => $details->married, 'icon' => 'fe-heart']
                                    ];
                                @endphp
                                @foreach($personalData as $item)
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center p-3 border rounded-3 hover-shadow-sm transition-all">
                                        <i class="{{ $item['icon'] }} text-primary me-3 font-20"></i>
                                        <div>
                                            <label class="text-muted small fw-bold text-uppercase mb-0 d-block">{{ $item['label'] }}</label>
                                            <p class="h6 mb-0">{{ $item['value'] }}</p>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="tab-pane fade" id="address" role="tabpanel">
                            <h5 class="header-title mb-4 text-primary">Location Information</h5>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3 text-center border">
                                        <i class="fe-map text-primary font-20 mb-2 d-block"></i>
                                        <label class="text-muted small fw-bold text-uppercase d-block">District</label>
                                        <span class="h6">{{ $details->district_name ?? 'Not Found' }}</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3 text-center border">
                                        <i class="fe-navigation text-primary font-20 mb-2 d-block"></i>
                                        <label class="text-muted small fw-bold text-uppercase d-block">Upazila</label>
                                        <span class="h6">{{ $details->upazila_name ?? 'Not Found' }}</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3 text-center border">
                                        <i class="fe-mail text-primary font-20 mb-2 d-block"></i>
                                        <label class="text-muted small fw-bold text-uppercase d-block">Post Code</label>
                                        <span class="h6">{{ $details->post_code }}</span>
                                    </div>
                                </div>
                                <div class="col-12 mt-3">
                                    <div class="p-4 border rounded-3 shadow-sm" style="background: #f8faff; border-left: 4px solid #3bafda !important;">
                                        <label class="text-muted small fw-bold text-uppercase d-block mb-1"><i class="fe-home me-1"></i> Full Address</label>
                                        <p class="h5 mb-0 text-dark" style="line-height: 1.6;">{{ $details->address }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="wallet" role="tabpanel">
                            <h5 class="header-title mb-4 text-primary">Financial Summary</h5>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="gradient-card bg-green p-4 rounded-4 shadow-sm text-white">
                                        <i class="fe-database font-24 mb-2 d-block"></i>
                                        <h3 class="fw-bold text-white mb-0">{{ $details->balance }}</h3>
                                        <p class="mb-0 opacity-75">Current Balance (BDT)</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="gradient-card bg-blue p-4 rounded-4 shadow-sm text-white">
                                        <i class="fe-users font-24 mb-2 d-block"></i>
                                        <h3 class="fw-bold text-white mb-0">{{ $details->referrer_id }}</h3>
                                        <p class="mb-0 opacity-75">Referrer ID</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="gradient-card bg-purple p-4 rounded-4 shadow-sm text-white">
                                        <i class="fe-gift font-24 mb-2 d-block"></i>
                                        <h3 class="fw-bold text-white mb-0">{{ $details->referrer_code }}</h3>
                                        <p class="mb-0 opacity-75">Referrer Code</p>
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
                                            <a href="{{ asset($url) }}" target="_blank" class="image-popup">
                                                <img src="{{ asset($url) }}" class="img-fluid" style="height: 160px; width: 100%; object-fit: cover;">
                                            </a>
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
    .rounded-4 { border-radius: 1.2rem !important; }
    .hover-lift { transition: all 0.2s ease; }
    .hover-lift:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.1) !important; }
    
    .icon-stack {
        height: 35px; width: 35px; background: rgba(0,0,0,0.05);
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 8px; font-size: 16px;
    }
    
    .premium-nav .nav-link { color: #6c757d; font-weight: 500; }
    .premium-nav .nav-link.active {
        background-color: #6658dd !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    }
    ul.nav.nav-pills.nav-justified.bg-light.p-1.rounded-pill.mb-4.premium-nav {
        background: #f0f0f0;
    }
    .document-card { transition: all 0.3s ease; }
    .document-card:hover { transform: scale(1.03); border-color: #3bafda !important; }

    .gradient-card.bg-green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
    .gradient-card.bg-blue { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); }
    .gradient-card.bg-purple { background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%); }
    .gradient-card { transition: transform 0.3s; cursor: pointer; }
    .gradient-card:hover { transform: translateY(-5px); }

    .header-title { border-left: 5px solid #3bafda; padding-left: 15px; font-weight: 800; }
    .transition-all { transition: all 0.3s ease; }
    .hover-shadow-sm:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.05); border-color: #3bafda !important; }
</style>
@endsection