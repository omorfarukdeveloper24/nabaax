<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Hello, looks like you have forgotten your password. From Bornoedu.com </title>
</head>
<body class="bg-white">
<div style="background:#ddd; width:100%;text-align:center !important">
<div style="background:#fff; width:90%;margin:0 auto !important">
     @php
        $customer_info = App\Models\School::where('id',$school_id)->first();
        $logo = App\Models\GeneralSetting::where('status',1)->first();
       @endphp
     <!--email template -->
    <table class="body-wrap" style="background:#fff; width: 100%; margin: 0;">
        <tbody style="background:#5FD27C;">
            <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 25px; margin: 0;border:0">
                <td style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;padding:20px 0">
                    Hi Dear <strong>{{$customer_info->name}}</strong> <br>
                   <h3 style="color:#fff;text-align:center;">Your Verify Code: {{ $customer_info->forgot }}</h3>
                </td>
            </tr>
        </tbody>
    </table>
    <table class="body-wrap" style="background:#fff; width: 100%;text-align:center;">
        <tbody>
            <tr style="text-align:center">
                <td style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                   <img src="{{asset($logo->white_logo)}}" style="width:180px;margin-top:15px">
                 </td>
            </tr>
        </tbody>
    </table>
    
   
   
    <table class="body-wrap" style="background:#fff; width: 100%; margin: 0;;text-align:center !important">
        <tbody style="background:#5FD27C;">
            <tr style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box;  margin: 0;border:0">
                <td style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; margin: 0;">
                   <p style="color:#fff;text-align:center;padding:20px 0;font-size:15px;letter-spacing:2px">@copyright {{date('Y')}} Bornoedu</p>
                 </td>
            </tr>
        </tbody>
    </table>
    
</div>
</div>
</body>
</html>