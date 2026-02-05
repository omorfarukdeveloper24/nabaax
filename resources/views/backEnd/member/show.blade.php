@extends('backEnd.layouts.master')
@section('title', 'Member Profile - ' . $details->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="page-title">Member Details</h4>
                <a href="{{ route('verifymember.index') }}" class="btn btn-secondary btn-sm"><i class="fe-arrow-left"></i> Back to List</a>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <div class="card border-primary">
                <div class="card-body d-flex flex-wrap justify-content-center gap-2">
                    <form action="{{ route('verifymember.status') }}" method="POST">
                        @csrf
                        <input type="hidden" name="hidden_id" value="{{ $details->id }}">
                        <input type="hidden" name="verified" value="1">
                        <input type="hidden" name="submit" value="1">
                        <button type="submit" class="btn btn-success px-4"><i class="fe-check-circle me-1"></i> Verify Member</button>
                    </form>

                    <form action="{{ route('verifymember.status') }}" method="POST">
                        @csrf
                        <input type="hidden" name="hidden_id" value="{{ $details->id }}">
                        <input type="hidden" name="verified" value="0"> <input type="hidden" name="submit" value="0">
                        <button type="submit" class="btn btn-warning px-4 text-white"><i class="fe-x-circle me-1"></i> Reject Member</button>
                    </form>

                    <form action="{{ route('verifymember.status') }}" method="POST">
                        @csrf
                        <input type="hidden" name="hidden_id" value="{{ $details->id }}">
                        <input type="hidden" name="verified" value="3">
                        <input type="hidden" name="submit" value="1">
                        <button type="submit" class="btn btn-danger px-4"><i class="fe-slash me-1"></i> Block Member</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4 col-lg-5">
            <div class="card text-center">
                <div class="card-body">
                    <div class="position-relative d-inline-block">
                        <img src="{{ asset($details->image ?? 'public/backEnd/assets/images/users/user-1.jpg') }}" 
                             class="rounded-circle avatar-xl img-thumbnail" alt="profile">
                        @if($details->verified == 1)
                            <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-success"><i class="fe-check"></i></span>
                        @endif
                    </div>

                    <h4 class="mb-1 mt-2">{{ $details->name }}</h4>
                    <p class="text-muted font-14">@ {{$details->username}}</p>

                    <div class="text-start mt-3">
                        <p class="text-muted mb-2 font-13"><strong>Phone:</strong> <span class="ms-2">{{ $details->phone }}</span></p>
                        <p class="text-muted mb-2 font-13"><strong>Email:</strong> <span class="ms-2">{{ $details->email }}</span></p>
                        <p class="text-muted mb-1 font-13"><strong>Join Date:</strong> <span class="ms-2">{{ $details->created_at->format('d M, Y') }}</span></p>
                    </div>

                    <div class="mt-3">
                        @if($details->verified == 1)
                            <span class="badge bg-soft-success text-success p-2 px-3">Verified Member</span>
                        @elseif($details->verified == 3)
                            <span class="badge bg-soft-danger text-danger p-2 px-3">Blocked Account</span>
                        @else
                            <span class="badge bg-soft-warning text-warning p-2 px-3">Pending/Rejected</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8 col-lg-7">
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-pills nav-fill bg-light p-1 rounded-pill mb-3" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active rounded-pill" id="personal-tab" data-bs-toggle="tab" href="#personal" role="tab">Personal Info</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link rounded-pill" id="address-tab" data-bs-toggle="tab" href="#address" role="tab">Address & Contact</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link rounded-pill" id="referral-tab" data-bs-toggle="tab" href="#referral" role="tab">Referral & Wallet</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link rounded-pill" id="verify-docs-tab" data-bs-toggle="tab" href="#verify-docs" role="tab">Verify Documents</a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane show active" id="personal" role="tabpanel">
                            <h5 class="mb-3 text-uppercase"><i class="mdi mdi-account-circle me-1"></i> Personal Detail</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1 fw-bold">NID Number</p>
                                    <p class="text-muted">{{ $details->nid_number ?? 'Not Provided' }}</p>
                                    <p class="mb-1 fw-bold">Blood Group</p>
                                    <p class="text-muted">{{ $details->blood ?? 'N/A' }}</p>
                                    <p class="mb-1 fw-bold">Gender</p>
                                    <p class="text-muted">{{ $details->gender }}</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1 fw-bold">Profession</p>
                                    <p class="text-muted">{{ $details->profession }}</p>
                                    <p class="mb-1 fw-bold">Monthly Income</p>
                                    <p class="text-muted">{{ $details->monthlyincome }}</p>
                                    <p class="mb-1 fw-bold">Marital Status</p>
                                    <p class="text-muted">{{ $details->married }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane" id="address" role="tabpanel">
                            <h5 class="mb-3 text-uppercase"><i class="mdi mdi-map-marker me-1"></i> Location Info</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1 fw-bold">District</p>
                                    <p class="text-muted">{{ $details->district }}</p>
                                    <p class="mb-1 fw-bold">Upazila</p>
                                    <p class="text-muted">{{ $details->upazila }}</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1 fw-bold">Post Code</p>
                                    <p class="text-muted">{{ $details->post_code }}</p>
                                    <p class="mb-1 fw-bold">Full Address</p>
                                    <p class="text-muted">{{ $details->address }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane" id="referral" role="tabpanel">
                            <h5 class="mb-3 text-uppercase"><i class="mdi mdi-wallet me-1"></i> Earnings & Referral</h5>
                            <div class="row text-center">
                                <div class="col-md-4 border-end">
                                    <h4 class="text-success">{{ $details->balance }} BDT</h4>
                                    <p class="text-muted mb-0">Current Balance</p>
                                </div>
                                <div class="col-md-4 border-end">
                                    <h4 class="text-primary">{{ $details->referrer_id }}</h4>
                                    <p class="text-muted mb-0">Referrer ID</p>
                                </div>
                                <div class="col-md-4">
                                    <h4 class="text-info">{{ $details->referrer_code }}</h4>
                                    <p class="text-muted mb-0">Referrer Code</p>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane" id="verify-docs" role="tabpanel">
                            <h5 class="mb-3 text-uppercase"><i class="mdi mdi-file-document me-1"></i> Submitted Documents</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Identity Type</th>
                                            <td><span class="badge bg-info">{{ strtoupper($details->type ?? 'None') }}</span></td>
                                        </tr>
                                        <tr>
                                            <th>NID / Birth Number</th>
                                            <td>{{ $details->verify_nid ?? ($details->birth_number ?? 'Not Provided') }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <div class="row mt-3">
                                @php
                                    // দেখার সুবিধার জন্য ইমেজ কলামগুলোর একটি অ্যারে
                                    $images = [
                                        'NID Front' => $details->nid_front_image,
                                        'NID Back' => $details->nid_back_image,
                                        'Birth Image' => $details->birth_image,
                                        'Selfie' => $details->salfy_image,
                                        'Passport' => $details->passport_image,
                                        'Driving Front' => $details->driving_front_image,
                                        'Driving Back' => $details->driving_back_image
                                    ];
                                @endphp

                                @foreach($images as $label => $path)
                                    @if($path)
                                        <div class="col-md-4 mb-3">
                                            <p class="fw-bold mb-1">{{ $label }}</p>
                                            <a href="{{ asset($path) }}" target="_blank">
                                                <img src="{{ asset($path) }}" class="img-fluid rounded border" style="max-height: 200px; width: 100%; object-fit: cover;">
                                            </a>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>


                    </div> </div> </div> </div> </div>
</div>
@endsection