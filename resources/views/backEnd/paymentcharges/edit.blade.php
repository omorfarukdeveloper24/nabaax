@extends('backEnd.layouts.master')
@section('title', 'Payment Charge Update')
@section('css')
    <link href="{{ asset('public/backEnd') }}/assets/libs/select2/css/select2.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ asset('public/backEnd') }}/assets/libs/summernote/summernote-lite.min.css" rel="stylesheet"
        type="text/css" />
@endsection
@section('content')
    <div class="container-fluid">

        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="page-title-right">
                        <a href="{{ route('paymentcharges.index') }}" class="btn btn-primary rounded-pill">Manage</a>
                    </div>
                    <h4 class="page-title">General Payment Charge Update</h4>
                </div>
            </div>
        </div>
        <!-- end page title -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <form action="{{ route('paymentcharges.update') }}" method="POST" class="row" data-parsley-validate=""
                            enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="id" value="{{ $edit_data->id }}">

                            <div class="col-sm-4">
                                <div class="form-group mb-3">
                                    <label for="min_deposit" class="form-label">Mimimum Deposit Limit *</label>
                                    <input type="number" class="form-control @error('min_deposit') is-invalid @enderror"
                                        name="min_deposit" value="{{ $edit_data->min_deposit }}" id="min_deposit" required="">
                                    @error('min_deposit')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <!-- col-end -->

                            <div class="col-sm-4">
                                <div class="form-group mb-3">
                                    <label for="min_withdraw" class="form-label">Mimimum Withdraw Limit *</label>
                                    <input type="number" class="form-control @error('min_withdraw') is-invalid @enderror"
                                        name="min_withdraw" value="{{ $edit_data->min_withdraw }}" id="min_withdraw" required="">
                                    @error('min_withdraw')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <!-- col-end -->

                            <div class="col-sm-4">
                                <div class="form-group mb-3">
                                    <label for="transfer_limit" class="form-label">Mimimum Transfer Limit *</label>
                                    <input type="number" class="form-control @error('transfer_limit') is-invalid @enderror"
                                        name="transfer_limit" value="{{ $edit_data->transfer_limit }}" id="transfer_limit" required="">
                                    @error('transfer_limit')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <!-- col-end -->

                            <div class="col-sm-6">
                                <div class="form-group mb-3">
                                    <label for="first_gen_bonus" class="form-label">First Generation Bonus *</label>
                                    <input type="number" class="form-control @error('first_gen_bonus') is-invalid @enderror"
                                        name="first_gen_bonus" value="{{ $edit_data->first_gen_bonus }}" id="first_gen_bonus" required="">
                                    @error('first_gen_bonus')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <!-- col-end -->

                            <div class="col-sm-6">
                                <div class="form-group mb-3">
                                    <label for="multi_gen_bonus" class="form-label">Multi Generation Bonus ( 2nd to 100th ) *</label>
                                    <input type="number" class="form-control @error('multi_gen_bonus') is-invalid @enderror"
                                        name="multi_gen_bonus" value="{{ $edit_data->multi_gen_bonus }}" id="multi_gen_bonus" required="">
                                    @error('multi_gen_bonus')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <!-- col-end -->

                            <div class="col-sm-6">
                                <div class="form-group mb-3">
                                    <label for="partner_own_bonus" class="form-label">Partner Program Cost *</label>
                                    <input type="number" class="form-control @error('partner_own_bonus') is-invalid @enderror"
                                        name="partner_own_bonus" value="{{ $edit_data->partner_own_bonus }}" id="partner_own_bonus" required="">
                                    @error('partner_own_bonus')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <!-- col-end -->
                            
                            <div class="col-sm-6">
                                <div class="form-group mb-3">
                                    <label for="partner_min_balance" class="form-label">Partner Program Minimum Balance *</label>
                                    <input type="number" class="form-control @error('partner_min_balance') is-invalid @enderror"
                                        name="partner_min_balance" value="{{ $edit_data->partner_min_balance }}" id="partner_min_balance" required="">
                                    @error('partner_min_balance')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <!-- col-end -->

                           
                            <div class="col-sm-6 mb-3">
                                <div class="form-group">
                                    <label for="status" class="d-block">Status</label>
                                    <label class="switch">
                                        <input type="checkbox" value="1" name="status"
                                            @if ($edit_data->status == 1) checked @endif>
                                        <span class="slider round"></span>
                                    </label>
                                    @error('status')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <!-- col end -->
                            <div>
                                <input type="submit" class="btn btn-success" value="Submit">
                            </div>

                        </form>

                    </div> <!-- end card-body-->
                </div> <!-- end card-->
            </div> <!-- end col-->
        </div>
    </div>
@endsection


@section('script')
    <script src="{{ asset('public/backEnd/') }}/assets/libs/parsleyjs/parsley.min.js"></script>
    <script src="{{ asset('public/backEnd/') }}/assets/js/pages/form-validation.init.js"></script>
    <script src="{{ asset('public/backEnd/') }}/assets/libs/select2/js/select2.min.js"></script>
    <script src="{{ asset('public/backEnd/') }}/assets/js/pages/form-advanced.init.js"></script>
    <!-- Plugins js -->
    <script src="{{ asset('public/backEnd/') }}/assets/libs//summernote/summernote-lite.min.js"></script>
    <script>
        $(".summernote").summernote({
            placeholder: "Enter Your Text Here",
        });
    </script>
@endsection
