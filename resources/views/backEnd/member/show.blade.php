@extends('backEnd.layouts.master')
@section('title', 'Member Profile - ' . $details->name)

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0/magnific-popup.min.css">

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="page-title fw-bold text-dark">Member Control Center</h4>
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
            <div class="card border-0 shadow-sm rounded-4 bg-glass">
                <div class="card-body p-3 d-flex flex-wrap justify-content-center align-items-center gap-3">
                    <span class="badge bg-soft-dark text-dark py-2 px-3 rounded-pill"><i class="fe-settings me-1"></i> Management Actions</span>
                    
                    <form action="{{ route('verifymember.status') }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="hidden_id" value="{{ $details->id }}">
                        <input type="hidden" name="verified" value="1">
                        <button type="submit" class="btn btn-success rounded-pill px-4 shadow hover-lift border-0" style="background: linear-gradient(135deg, #1D976C 0%, #93F9B9 100%);">
                            <i class="fe-check-circle me-1"></i> Approve
                        </button>
                    </form>

                    <form action="{{ route('verifymember.status') }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="hidden_id" value="{{ $details->id }}">
                        <input type="hidden" name="verified" value="0">
                        <button type="submit" class="btn btn-warning text-white rounded-pill px-4 shadow hover-lift border-0" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <i class="fe-x-circle me-1"></i> Reject
                        </button>
                    </form>

                    <form action="{{ route('verifymember.status') }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="hidden_id" value="{{ $details->id }}">
                        <input type="hidden" name="verified" value="3">
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
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-4">
                <div class="profile-header-premium"></div>
                <div class="card-body pt-0 text-center">
                    <div class="mt-n5 position-relative d-inline-block">
                        <img src="{{ asset($details->image ?? 'public/backEnd/assets/images/users/user-1.jpg') }}" 
                             class="rounded-circle border-4 border-white shadow-lg avatar-xxl profile-img-glow" 
                             style="width: 140px; height: 140px; object-fit: cover; margin-top: -70px;">
                        @if($details->verified == 1)
                            <span class="position-absolute bottom-0 end-0 bg-success border border-3 border-white rounded-circle p-1 pulse-animation" title="Verified Member">
                                <i class="fe-check text-white font-12"></i>
                            </span>
                        @endif
                    </div>

                    <h3 class="mt-3 mb-1 fw-bold text-dark">{{ $details->name }}</h3>
                    <p class="text-muted mb-3 font-15">@ {{$details->username}}</p>

                    <div class="text-start info-box-premium rounded-4 p-3 mb-2">
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon-shape bg-soft-info text-info rounded-3 me-3"><i class="fe-phone"></i></div>
                            <div><small class="text-muted d-block">Phone</small><span class="fw-bold">{{ $details->phone }}</span></div>
                        </div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon-shape bg-soft-primary text-primary rounded-3 me-3"><i class="fe-mail"></i></div>
                            <div><small class="text-muted d-block">Email</small><span class="fw-bold">{{ $details->email }}</span></div>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="icon-shape bg-soft-success text-success rounded-3 me-3"><i class="fe-calendar"></i></div>
                            <div><small class="text-muted d-block">Joined</small><span class="fw-bold">{{ $details->created_at->format('d M, Y') }}</span></div>
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
                            <h5 class="header-title mb-4 text-primary">Personal Records</h5>
                            <div class="row g-3">
                                @php 
                                    $personalData = [
                                        ['label' => 'NID Number', 'value' => $details->nid_number ?? 'N/A', 'icon' => 'fe-credit-card'],
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
                            <h5 class="header-title mb-4 text-primary">Location Details</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3">
                                        <label class="text-muted small fw-bold d-block">DISTRICT</label>
                                        <span class="fw-bold">{{ $details->district }}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3">
                                        <label class="text-muted small fw-bold d-block">UPAZILA</label>
                                        <span class="fw-bold">{{ $details->upazila }}</span>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="p-3 border rounded-3">
                                        <label class="text-muted small fw-bold d-block"><i class="fe-map me-1"></i> FULL ADDRESS</label>
                                        <p class="mb-0">{{ $details->address }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="wallet" role="tabpanel">
                            <h5 class="header-title mb-4 text-primary">Financial Portfolio</h5>
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
                                <h5 class="header-title text-primary mb-0">Identity Proofs</h5>
                                <span class="badge bg-soft-info text-info p-2 px-3 rounded-pill">Type: {{ strtoupper($details->type ?? 'N/A') }}</span>
                            </div>
                            
                            <div class="row popup-gallery">
                                @php
                                    $documents = [
                                        'NID Front' => $details->nid_front_image,
                                        'NID Back' => $details->nid_back_image,
                                        'Selfie' => $details->salfy_image,
                                        'Passport' => $details->passport_image,
                                        'Driving Front' => $details->driving_front_image
                                    ];
                                @endphp
                                @foreach($documents as $name => $url)
                                    @if($url)
                                    <div class="col-md-4 mb-4">
                                        <div class="doc-container rounded-4 shadow-sm border overflow-hidden">
                                            <div class="doc-overlay">
                                                <a href="{{ asset($url) }}" class="btn btn-light btn-sm rounded-pill viewer-link" title="{{ $name }}">
                                                    <i class="fe-eye me-1"></i> View
                                                </a>
                                            </div>
                                            <img src="{{ asset($url) }}" class="img-fluid" style="height: 160px; width: 100%; object-fit: cover;">
                                            <div class="p-2 bg-white d-flex justify-content-between align-items-center">
                                                <small class="fw-bold text-muted">{{ $name }}</small>
                                                <a href="{{ asset($url) }}" download="{{ $name }}-{{ $details->name }}" class="btn btn-xs btn-primary rounded-circle">
                                                    <i class="fe-download font-12"></i>
                                                </a>
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
    /* 1st Point: Professional Header Background */
    .profile-header-premium {
        height: 140px;
        background: linear-gradient(45deg, #2193b0, #6dd5ed, #1e3c72);
        background-size: 400% 400%;
        animation: gradientBG 15s ease infinite;
    }
    @keyframes gradientBG {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    /* 3rd Point: Wallet Premium Gradients */
    .gradient-card.bg-green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
    .gradient-card.bg-blue { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); }
    .gradient-card.bg-purple { background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%); }
    .gradient-card { transition: transform 0.3s; cursor: pointer; }
    .gradient-card:hover { transform: translateY(-5px); }

    /* Other Improvements */
    .info-box-premium { background: #f8f9fa; border: 1px solid #eef2f7; }
    .icon-shape { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
    .rounded-4 { border-radius: 1.2rem !important; }
    .hover-lift { transition: all 0.3s; }
    .hover-lift:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
    
    /* Doc Viewer Styling */
    .doc-container { position: relative; }
    .doc-overlay {
        position: absolute; top: 0; left: 0; width: 100%; height: 160px;
        background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center;
        opacity: 0; transition: 0.3s; z-index: 2;
    }
    .doc-container:hover .doc-overlay { opacity: 1; }
    .profile-img-glow { box-shadow: 0 0 20px rgba(0,0,0,0.15); }
    .header-title { border-left: 5px solid #3bafda; padding-left: 15px; font-weight: 800; }
    .transition-all { transition: all 0.3s ease; }
    .hover-shadow-sm:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.05); border-color: #3bafda !important; }
    
    .pulse-animation { animation: pulse 2s infinite; }
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(24, 210, 110, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(24, 210, 110, 0); }
        100% { box-shadow: 0 0 0 0 rgba(24, 210, 110, 0); }
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0/jquery.magnific-popup.min.js"></script>
<script>
    $(document).ready(function() {
        $('.popup-gallery').magnificPopup({
            delegate: '.viewer-link',
            type: 'image',
            tLoading: 'Loading image #%curr%...',
            mainClass: 'mfp-img-mobile',
            gallery: {
                enabled: true,
                navigateByImgClick: true,
                preload: [0,1]
            },
            image: {
                tError: '<a href="%url%">The image #%curr%</a> could not be loaded.',
            }
        });
    });
</script>
@endsection