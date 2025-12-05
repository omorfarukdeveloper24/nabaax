<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Log In | {{ $generalsetting->name }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Websolution IT" name="author" />
    <meta content="{{ $generalsetting->meta_description }}" name="description" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <!-- App favicon -->
    <link rel="shortcut icon" href="{{ asset($generalsetting->favicon) }}">

    <!-- Bootstrap css -->
    <link href="{{ asset('public/backEnd/') }}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <!-- App css -->
    <link href="{{ asset('public/backEnd/') }}/assets/css/app.min.css" rel="stylesheet" type="text/css"
        id="app-style" />
    <!-- icons -->
    <link href="{{ asset('public/backEnd/') }}/assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <!-- Head js -->
    <link href="{{ asset('public/backEnd/') }}/assets/css/custom.css" rel="stylesheet" type="text/css" />
    <script src="{{ asset('public/backEnd/') }}/assets/js/head.js"></script>

    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 16px;
            font-weight: 400;
            color: #666666;
            background: #eaeff4;
        }

        .wrapper {
            margin: 0 auto;
            max-width: 1140px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            width: 573px;
            background: #ffffff;
        }

        .container_innner {
            position: relative;
            width: 100%;
            display: flex;
        }



        .admin_logo {
            width: 60px;
            border-radius: 50px;
            overflow: hidden;
        }

        .credit {
            position: relative;
            margin: 25px auto 0 auto;
            width: 100%;
            text-align: center;
            color: #666666;
            font-size: 16px;
            font-weight: 400;
        }

        .credit a {
            color: #222222;
            font-size: 16px;
            font-weight: 600;
        }

        .col-left,
        .col-right {
            padding: 28px;
            display: flex;
        }

        .col-left {
            width: 60%;
            -webkit-clip-path: polygon(0 0, 0% 100%, 100% 0);
            clip-path: polygon(0 0, 0% 100%, 100% 0);
            background: #000000;
        }

        .col-right {
            padding: 60px 30px;
            width: 50%;
            margin-left: -10%;
        }

        @media (max-width: 575.98px) {
            .container_innner {
                flex-direction: column;
                box-shadow: none;
            }

            .col-left,
            .col-right {
                width: 100%;
                margin: 0;
                -webkit-clip-path: none;
                clip-path: none;
            }

            .col-right {
                padding: 30px;
            }

            .wrapper {
                width: 355px;
            }
        }

        .login-text {
            position: relative;
            width: 100%;
            color: #ffffff;
        }

        .login-text h2 {
            margin: 0 0 15px 0;
            font-size: 30px;
            font-weight: 700;
        }

        .login-text p {
            margin: 0 0 20px 0;
            font-size: 16px;
            font-weight: 500;
            line-height: 22px;
        }

        .login-text .btn {
            display: inline-block;
            padding: 7px 20px;
            font-size: 16px;
            letter-spacing: 1px;
            text-decoration: none;
            border-radius: 30px;
            color: #ffffff;
            outline: none;
            border: 1px solid #ffffff;
            box-shadow: inset 0 0 0 0 #ffffff;
            transition: 0.3s;
            -webkit-transition: 0.3s;
        }

        .login-text .btn:hover {
            color: #44c7f5;
            box-shadow: inset 150px 0 0 0 #ffffff;
        }

        .login-form {
            position: relative;
            width: 100%;
        }

        .login-form h2 {
            margin: 0 0 15px 0;
            font-size: 22px;
            font-weight: 700;
        }

        .login-form p {
            margin: 0 0 10px 0;
            text-align: left;
            color: #666666;
            font-size: 15px;
        }

        .login-form p:last-child {
            margin: 0;
            padding-top: 3px;
        }

        .login-form p a {
            color: #44c7f5;
            font-size: 14px;
            text-decoration: none;
        }

        .login-form label {
            display: block;
            width: 100%;
            margin-bottom: 2px;
            letter-spacing: 0.5px;
        }

        .login-form p:last-child label {
            width: 60%;
            float: left;
        }

        .login-form label span {
            color: #ff574e;
            padding-left: 2px;
        }

        .login-form input {
            display: block;
            width: 100%;
            height: 35px;
            padding: 0 10px;
            outline: none;
            border: 1px solid #cccccc;
            border-radius: 30px;
        }

        .login-form input:focus {
            border-color: #ff574e;
        }

        .login-form button,
        .login-form input[type="submit"] {
            display: inline-block;
            width: 100%;
            margin-top: 5px;
            color: #44c7f5;
            font-size: 16px;
            letter-spacing: 1px;
            cursor: pointer;
            background: transparent;
            border: 1px solid #44c7f5;
            border-radius: 30px;
            box-shadow: inset 0 0 0 0 #44c7f5;
            transition: 0.3s;
            -webkit-transition: 0.3s;
        }

        .login-form button:hover,
        .login-form input[type="submit"]:hover {
            color: #ffffff;
            box-shadow: inset 250px 0 0 0 #44c7f5;
        }
    </style>

</head>

<body class="authentication-bg authentication-bg-pattern">

    <div class="account-pages">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6 col-xl-4">
                    <div class="">
                        <div class="">
                            <form method="POST" action="{{ route('auth.login') }}">
                                @csrf
                                <div class="wrapper">
                                    <div class="container_innner">
                                        <div class="col-left">

                                            <div class="login-text">
                                                <div class="admin_logo">
                                                    <img src="https://www.bornoedu.com/public/assets/uploads/logo/17268024241.png"
                                                        alt="">
                                                </div>
                                                <h2 style="color: #fff">Welcome <br> Back</h2>
                                            </div>
                                        </div>
                                        <div class="col-right">
                                            <div class="login-form">
                                                <h2>Login</h2>
                                                <p>
                                                    <label>Email address<span>*</span></label>
                                                    <input type="text" name="email" placeholder="Username or Email"
                                                        required>
                                                </p>
                                                {{-- <p>
                                                    <label>Password<span>*</span></label>
                                                    <input type="password" name="password" placeholder="Password"
                                                        required>
                                                </p> --}}
                                                <p style="position: relative;">
                                                    <label>Password<span>*</span></label>
                                                    <input type="password" id="password" name="password"
                                                        placeholder="Password" required>
                                                    <span id="togglePassword"
                                                        style="position: absolute; top: 29px; right: 6px; cursor: pointer;">
                                                        👁️
                                                    </span>
                                                </p>
                                                <p>
                                                    <input type="submit" value="Sign In" />
                                                </p>
                                                <p>
                                                    <a href="">Forget Password?</a>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="{{ asset('public/backEnd/') }}/assets/js/vendor.min.js"></script>
    <script src="{{ asset('public/backEnd/') }}/assets/js/app.min.js"></script>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            // Optional: change icon
            this.textContent = type === 'password' ? '👁️' : '🙈';
        });
    </script>


</body>

</html>
