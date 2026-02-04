@extends('backEnd.layouts.master')
@section('title', 'Member Details - ' . $details->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Member Profile: {{ $details->name }}</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card text-center">
                <div class="card-body">
                    <img src="{{ asset($details->image ?? 'public/backEnd/assets/images/users/user-1.jpg') }}" 
                         class="rounded-circle avatar-xl img-thumbnail" alt="profile-image">

                    <h4 class="mb-0">{{ $details->name }}</h4>
                    <p class="text-muted">@ {{$details->username}}</p>

                    <div class="text-start mt-3">
                        <p class="text-muted mb-2 font-13"><strong>Full Name :</strong> <span class="ms-2">{{ $details->name }}</span></p>
                        <p class="text-muted mb-2 font-13"><strong>Mobile :</strong><span class="ms-2">{{ $details->phone }}</span></p>
                        <p class="text-muted mb-2 font-13"><strong>Email :</strong> <span class="ms-2">{{ $details->email }}</span></p>
                        <p class="text-muted mb-1 font-13"><strong>Location :</strong> <span class="ms-2">{{ $details->address }}, {{ $details->city }}</span></p>
                    </div>
                    
                    <div class="mt-3">
                        @if($details->status == 1)
                            <span class="badge bg-success">Active Member</span>
                        @else
                            <span class="badge bg-danger">Inactive/Pending</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3 text-uppercase bg-light p-2"><i class="mdi mdi-account-circle me-1"></i> Personal Information</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr><th>NID Number</th><td>: {{ $details->nid_number ?? 'N/A' }}</td></tr>
                                <tr><th>Blood Group</th><td>: {{ $details->blood ?? 'N/A' }}</td></tr>
                                <tr><th>Gender</th><td>: {{ $details->gender }}</td></tr>
                                <tr><th>Religion</th><td>: {{ $details->religion }}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr><th>Profession</th><td>: {{ $details->profession }}</td></tr>
                                <tr><th>Monthly Income</th><td>: {{ $details->monthlyincome }}</td></tr>
                                <tr><th>Nationality</th><td>: {{ $details->nationality }}</td></tr>
                                <tr><th>Marital Status</th><td>: {{ $details->married }}</td></tr>
                            </table>
                        </div>
                    </div>

                    <h5 class="mb-3 text-uppercase bg-light p-2"><i class="mdi mdi-office-building me-1"></i> Address & Referral</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>District:</strong> {{ $details->district }}</p>
                            <p><strong>Upazila:</strong> {{ $details->upazila }}</p>
                            <p><strong>Post Code:</strong> {{ $details->post_code }}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Referrer ID:</strong> {{ $details->referrer_id }}</p>
                            <p><strong>Referrer Code:</strong> {{ $details->referrer_code }}</p>
                            <p><strong>Wallet Balance:</strong> <span class="text-success fw-bold">{{ $details->balance }} BDT</span></p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('verifymember.index') }}" class="btn btn-secondary">Back to List</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection