@extends('frontEnd.layouts.master')
@section('title','Login')
@section('content')
<section class="auth-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-sm-7">
                <div class="main__login_sec">
                    <div class="login__left_img">
                    <img src="{{asset('public/frontEnd/images/login.png')}}">
                </div>
                <div class="form-content">
                    <p class="auth-title">Welcome Back ! </p>
                    <p class="auth-title_span">Login to your account.</p>
                    <form action="{{route('customer.signin')}}" method="POST"  data-parsley-validate="">
                        @csrf
                        <div class="form-group mb-3">
                            <label for="phone">Mobile Number Or Email *</label>
                            <input type="text" id="phone" class="form-control @error('phone') is-invalid @enderror"  placeholder="Enter your number or email" name="phone" value="{{ old('phone') }}"  required>
                            @error('phone')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <!-- col-end -->
                        <div class="form-group mb-3">
                            <label for="password">Password *</label>
                            <input type="password" id="password-field" class="form-control @error('password') is-invalid @enderror" placeholder="Enter your password" name="password" value="{{ old('password') }}"  required>
                             <span toggle="#password-field" class="fa fa-fw fa-eye field-icon toggle-password"></span>
                            @error('password')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <!-- col-end -->
                       <div class="pass__forget">
                           <div class="check_box">
                                <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
                                <label class="form-check-label" for="flexCheckDefault">
                                   Remember Me
                                </label>
                           </div>
                           <div class="forget">
                                <a href="{{route('customer.forgot.password')}}" class="forget-link"><i class="fa-solid fa-unlock"></i> Forgot Password?</a>
                           </div>
                       </div>
                        <div class="form-group mb-3">
                            <button class="submit-btn"> Login </button>
                        </div>
                     <!-- col-end -->
                     </form>
                     <div class="register-now no-account">
                        <p> You Have No Account?  <a href="{{route('customer.register')}}"><i data-feather="edit-3"></i> Click To Register</a></p>
                       
                    </div>
                </div>
                </div>
                <div class="back__page"><a href="{{route('home')}}"><p><i class="fa-solid fa-arrow-left-long"></i> Back to Previous Page</p></a></div>
            </div>
        </div>
    </div>
</section>
@endsection
@push('script')
<script src="{{asset('public/frontEnd/')}}/js/parsley.min.js"></script>
<script src="{{asset('public/frontEnd/')}}/js/form-validation.init.js"></script>
<script>
    $(".toggle-password").click(function() {

  $(this).toggleClass("fa-eye fa-eye-slash");
  var input = $($(this).attr("toggle"));
  if (input.attr("type") == "password") {
    input.attr("type", "text");
  } else {
    input.attr("type", "password");
  }
});
</script>
@endpush