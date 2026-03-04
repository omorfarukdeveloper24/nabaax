<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Member;
use App\Models\Post;
use App\Models\SmsGateway;
use App\Models\GeneralSetting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Brian2694\Toastr\Facades\Toastr;
use App\Models\MemberVerify;
use App\Models\FollowBoost;
use App\Models\CreatePage;
use App\Models\Notification;
use App\Models\DeviceToken;
use App\Models\MiniAd;
use App\Models\Follow;
use App\Models\PostBoost;
use App\Models\Memberbackup;
use App\Models\PaymentChargeSetting;
use App\Models\CustomerPayHistory;
use App\Models\AdminPayHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Google\Client;
use App\Traits\NotificationTrait;
use App\Jobs\DistributePartnerBonus;
use Illuminate\Support\Facades\Cache;

class MemberController extends Controller
{
    use NotificationTrait;
    function __construct()
    {
            $this->middleware("auth.jwt", [
                "except" => [
                    "store",
                    "verify",
                    "checkLogin",
                    "membersearch",
                    "forgot_password",
                    "forgot_verify",
                    "resendotp",
                    "memberVerify",
                    "phone_verify",
                    "resendcode",
                    "signin",
                    "logout",
                    "country",
                    "schoolforgot_verify",
                    "schoolforgot_password",
                    "passResetVerify",
                    "forgot_resend",
                    "new_password",
                    "notification",
                    "pagelist",
                    "setting",
                    "sendNotification",
                    "messageWithNotification",
                ],
     ]);
    }

    // public function signin(Request $request)
    //     {
    //         $validator = Validator::make($request->all(), [
    //             "phone" => "required",
    //             "password" => "required",
    //         ]);
        
    //         if ($validator->fails()) {
    //             return response()->json(
    //                 [
    //                     "error" => "validation_error",
    //                     "message" => $validator->errors(),
    //                 ],
    //                 200
    //             );
    //         }
        
         
    //       $auth_check = Member::where('phone', $request->phone)->first();
    //         if (!$auth_check) {
    //             return response()->json(
    //                 [
    //                     "error" => "User not found",
    //                      'message'=>'Data not found , Enter valid information'
    //                 ],
    //                 404
    //             );
    //         }
        
    //       if ($auth_check->status == 0) {
    //             return response()->json(
    //                 [
    //                     "error" => "Account Pending",
    //                     "message" => "Your account is pending approval. Please wait."
    //                 ],
    //                 403
    //             );
    //         }

    //         $credentials_phone = [
    //             "phone" => $request->phone,
    //             "password" => $request->password,
    //         ];
        
    //         $credentials_email = [
    //             "email" => $request->phone,
    //             "password" => $request->password,
    //         ];
        
    //         try {
    //             $token = null;
    //             if ($auth_check->phone === $request->phone) {
    //                 $token = Auth::guard("member")->attempt($credentials_phone);
    //             }
    //             if (!$token) {
    //                 return response()->json(
    //                     [
    //                         "error" => "Invalid Credentials",
    //                          'message'=>'Check your Phone number or Password'
    //                     ],
    //                     401
    //                 );
    //             }
    //         } catch (JWTException $e) {
    //             return response()->json(
    //                 [
    //                     "error" => "Could not create token",
    //                     'message'=>'Something went wrong!'
    //                 ],
    //                 500
    //             );
    //         }
        
    //         return response()->json(
    //             [
    //                 "status" => "success",
    //                 'message'=>'Login successfully',
    //                 "token" => $token,
    //                 "data" => $auth_check,
    //             ],
    //             200
    //     );
    // }
    
    
    
    
    public function signin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "phone" => "required",
            "password" => "required",
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                "error" => "validation_error",
                "message" => $validator->errors(),
            ], 200);
        }
    
        $auth_check = Member::where('phone', $request->phone)
            ->orWhere('username', $request->phone)
            ->first();
    
        if (!$auth_check) {
            return response()->json([
                "error" => "User not found",
                'message' => 'Data not found, Enter valid information'
            ], 404);
        }

    
        if ($auth_check->status == 0) {
            return response()->json([
                "error" => "Account Pending",
                "message" => "Your account is pending approval. Please wait Some Minute."
            ], 403);
        }
    
        $credentials_phone = [
            "phone" => $request->phone,
            "password" => $request->password,
        ];
    

        $credentials_username = [
            "username" => $request->phone,
            "password" => $request->password,
        ];
    
        try {
            $token = null;
    
            if ($auth_check->phone === $request->phone) {
                $token = Auth::guard("member")->attempt($credentials_phone);
            } elseif ($auth_check->username === $request->phone) {
                $token = Auth::guard("member")->attempt($credentials_username);
            }
    
            if (!$token) {
                return response()->json([
                    "error" => "Invalid Credentials",
                    'message' => 'Check your Phone/Username or Password'
                ], 401);
            }
        } catch (JWTException $e) {
            return response()->json([
                "error" => "Could not create token",
                'message' => 'Something went wrong!'
            ], 500);
        }
    
        return response()->json([
            "status" => "success",
            'message' => 'Login successfully',
            "token" => $token,
            "data" => $auth_check,
        ], 200);
    }

    public function logout(Request $request)
    {
        try {
            
            $member = Auth::guard('member')->user();

            if ($member) {
               
                DeviceToken::where('member_id', $member->id)->delete();

                

                return response()->json([
                    'success' => true,
                    'message' => 'Logged out successfully and device tokens removed.'
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    
    
    
    
   public function loginCheck(Request $request)
    {
        if (Auth::guard('member')->check()) {
            $member = Auth::guard('member')->user();
            return response()->json([
                'status' => true,
                'message' => 'Member is logged in successfully.',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Member is not logged in.',
            ], 401);
        }
    }

    
    public function membersearch(Request $request)
    {
        $members = Member::select('id', 'name', 'username', 'phone', 'email', 'image')
            ->where('status', 1); 
    
        if ($request->keyword) {
            $keyword = $request->keyword;
    
            $members = $members->where(function ($query) use ($keyword) {
                $query->where('name', 'LIKE', "%{$keyword}%")
                      ->orWhere('username', 'LIKE', "%{$keyword}%")
                      ->orWhere('phone', 'LIKE', "%{$keyword}%");
            });
        }
    
        $members = $members->latest()->take(20)->get();
    
       
        if (empty($request->keyword)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please enter a keyword to search.',
                'data' => []
            ]);
        }
    
        
        return response()->json([
            'status' => 'success',
            'message' => count($members) > 0 ? 'Members found successfully.' : 'No members found.',
            'data' => $members
        ]);
    }
    
    public function country(){
        try {
        $countries = DB::table('countries')
            ->select('id', 'name', 'iso2', 'iso3', 'numeric_code')
            ->orderBy('name', 'asc')
            ->get(); 

        return response()->json([
            'success' => 'success',
            'message' => 'Country list loaded successfully',
            'data'    => $countries
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => "failed",
            'message' => 'Something went wrong',
            'error'   => $e->getMessage()
        ], 500);
    }
    }
    
    
    
   public function list()
    {
        $member = Auth::guard("member")->user();
       if (!$member) {
        return response()->json([
            'status' => failed,
                'message' => 'Unauthorized user'
            ], 401);
        }
        $members = Member::where('approved',1)->paginate(20);
        return response()->json(['status'=>'success','data'=>$members]);
    }

    private function generateReferrerCode() {
        $startLetters = Str::lower(Str::random(4));
        $now = now();
        $dateTime = $now->format('ymdHis'); 

        return $startLetters . $dateTime;
    }
    


    // public function store(Request $request) 
    // {

    //     $validator = Validator::make($request->all(), [
    //         'name'     => 'required|string|max:255',
    //         'phone'    => 'required|string|max:20|unique:members,phone',
    //         'username' => 'required|string|max:50|unique:members,username',
    //         'password' => 'required|string|min:6',
    //         'partner_code' => 'nullable|exists:members,referrer_code',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status'  => 'faield',
    //             'message' => $validator->errors()->first(), 
    //             'data'    => null
    //         ]);
    //     }
        
        
        
    //     if ($request->password !== $request->confirm_pass) {
    //         return response()->json([
    //             'status'  => 'failed', 
    //             'message' => 'Password and Confirm Password do not match',
    //             'data'    => null
    //         ]);
    //     }
        
        
    //     $referrerMember = Member::where('referrer_code', $request->partner_code)->first();
    //     $referrerMemberId = $referrerMember ? $referrerMember->id : null;
        
        

    //     $member = Member::create([
    //         'name'          => $request->name,
    //         'username'      => $request->username,
    //         'phone'         => $request->phone,
    //         'password'      => Hash::make($request->password),
    //         'balance'       => 0,
    //         'referrer_code' => $this->generateReferrerCode(), 
    //         'phoneverify'   => rand(111111, 999999),
    //         'only_reffer'   => $referrerMemberId, 
    //     ]);
        
        
    //     $site_setting = GeneralSetting::where('status', 1)->select('name', 'white_logo', 'status')->first();
    //     $sms_gateway = SmsGateway::where(['status' => 1])->first();
    //     if ($sms_gateway) {
    //         $url = "$sms_gateway->url";
    //         $data = [
    //             "api_key" => "$sms_gateway->api_key",
    //             "number" => $member->phone,
    //             "type" => 'text',
    //             "senderid" => "$sms_gateway->senderid",
    //             "message"  => "Dear {$member->name},\r\nYour verification code (OTP) is: {$member->phoneverify}\r\nThank you for using {$site_setting->name}!\r\nPowered by Safoan."

    //         ];
    //         $ch = curl_init();
    //         curl_setopt($ch, CURLOPT_URL, $url);
    //         curl_setopt($ch, CURLOPT_POST, 1);
    //         curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    //         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //         $response = curl_exec($ch);
    //         curl_close($ch);
    //     }

    //     Toastr::success('Success', 'Verify code send successfully');
        
        

    //     return response()->json([
    //         'status'  => 'success',
    //         'message' => 'Member created successfully!',
    //         'data'    => $member,
    //     ]);
    // }
    

    public function store(Request $request) 
    {
        // ১. ভ্যালিডেশন
        $validator = Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'phone'        => 'required|string|max:20|unique:members,phone',
            'username'     => 'required|string|max:50|unique:members,username',
            'password'     => 'required|string|min:6',
            'partner_code' => 'nullable|exists:members,referrer_code',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'failed', // বানান ঠিক করা হয়েছে (faield -> failed)
                'message' => $validator->errors()->first(), 
                'data'    => null
            ]);
        }

        // ২. পাসওয়ার্ড ম্যাচিং চেক
        if ($request->password !== $request->confirm_pass) {
            return response()->json([
                'status'  => 'failed', 
                'message' => 'Password and Confirm Password do not match',
                'data'    => null
            ]);
        }

        DB::beginTransaction(); // ডাটাবেস ট্রানজেকশন শুরু

        try {
            // ৩. রেফারার মেম্বার খুঁজে বের করা
            $referrerMember = Member::where('referrer_code', $request->partner_code)->first();
            $referrerMemberId = $referrerMember ? $referrerMember->id : null;

            // ৪. মেম্বার তৈরি করা
            $member = Member::create([
                'name'          => $request->name,
                'username'      => $request->username,
                'phone'         => $request->phone,
                'password'      => Hash::make($request->password),
                'balance'       => 0,
                'referrer_code' => $this->generateReferrerCode(), 
                'phoneverify'   => rand(111111, 999999),
                'only_reffer'   => $referrerMemberId, 
            ]);

            // ৫. SMS পাঠানো
            $site_setting = GeneralSetting::where('status', 1)->select('name', 'white_logo', 'status')->first();
            $sms_gateway = SmsGateway::where(['status' => 1])->first();

            if ($sms_gateway) {
                $url = $sms_gateway->url;
                $data = [
                    "api_key"  => $sms_gateway->api_key,
                    "number"   => $member->phone,
                    "type"     => 'text',
                    "senderid" => $sms_gateway->senderid,
                    "message"  => "Dear {$member->name},\r\nYour Registration verification code (OTP) is: {$member->phoneverify}\r\nThank you for using {$site_setting->name}!\r\nPowered by Safoan."
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $response = curl_exec($ch);
                curl_close($ch);
            }

            DB::commit(); // সব ঠিক থাকলে ডাটা সেভ হবে
            Toastr::success('Success', 'Verify code send successfully');

            return response()->json([
                'status'  => 'success',
                'message' => 'Member created successfully!',
                'data'    => $member,
            ]);

        } catch (\Exception $e) {
            DB::rollBack(); // কোনো এরর হলে ডাটা সেভ হবে না (আগের অবস্থায় ফিরে যাবে)
            
            return response()->json([
                'status'  => 'failed',
                'message' => 'Something went wrong! ' . $e->getMessage(), // ডেভেলপমেন্ট মোডে মেসেজটি দেখতে পাবেন
                'data'    => null
            ]);
        }
    }

    

    
    
    
    // public function forgot_password(Request $request)
    // {
    //     // return $request->all();
        
    //     $validator = Validator::make($request->all(), [
    //         'phone' => 'required|string|max:12',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => 'failed',
    //             'type' => 'validation_error',
    //             'message' => $validator->errors()->first(),
    //             'errors' => $validator->errors(),
    //         ], 422);
    //     }
      
    //     $user = Member::where('phone', $request->phone)->select('id', 'phone', 'forgot')->first();
        
     
    //     if (! $user) {
    //         return response()->json([
    //             'status' => 'failed',
    //             'message' => 'Phone number not found',
    //         ], 404);
    //     }

    //     $otp = mt_rand(100000, 999999);
    //     $user->forgot = $otp;
    //     $user->save();

    //     try {
    //         $site_setting = GeneralSetting::where('status', 1)
    //             ->select('name', 'white_logo', 'status')
    //             ->first();

    //         $sms_gateway = SmsGateway::where('status', 1)->first();

    //         if ($sms_gateway) {
    //             $url = $sms_gateway->url;
    //             $data = [
    //                 'api_key'  => $sms_gateway->api_key,
    //                 'number'   => $user->phone,
    //                 'type'     => 'text',
    //                 'senderid' => $sms_gateway->senderid,
    //                 'message'  => "Dear {$user->name},\r\nYour Forget verification code (OTP) is: {$otp}\r\nThank you for using " . ($site_setting->name ?? 'our service') . "!",
    //             ];
    //             $ch = curl_init();
    //             curl_setopt($ch, CURLOPT_URL, $url);
    //             curl_setopt($ch, CURLOPT_POST, 1);
    //             curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    //             curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //             curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //             $response = curl_exec($ch);

    //             if (curl_errno($ch)) {
    //                 $curlErr = curl_error($ch);
    //                 curl_close($ch);;
    //                 return response()->json([
    //                     'status' => 'failed',
    //                     'message' => 'Failed to send OTP SMS',
    //                 ], 500);
    //             }
    //             curl_close($ch);
    //         }

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'OTP sent to your phone number',
    //             'phone' => $user->phone,
    //         ], 200);

    //     } catch (Exception $e) {
    //         Log::error('Forgot password error: '.$e->getMessage());
    //         return response()->json([
    //             'status' => 'failed',
    //             'message' => 'Failed to send OTP',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function forgot_password(Request $request)
    {
        // ১. ভ্যালিডেশন
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $ip = $request->ip();
        $phone = $request->phone;

        // ২. লোকেশন ডাটা সংগ্রহ
        $locationData = ['ip' => $ip, 'city' => 'Unknown', 'country' => 'Unknown', 'isp' => 'Unknown'];
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 3]]);
            $response = file_get_contents("http://ip-api.com/json/{$ip}", false, $ctx);
            if ($response) {
                $apiData = json_decode($response);
                if ($apiData->status == 'success') {
                    $locationData['city']    = $apiData->city;
                    $locationData['country'] = $apiData->country;
                    $locationData['isp']     = $apiData->isp;
                }
            }
        } catch (\Exception $e) { }

        // ৩. বিকাশ স্টাইল লিমিট চেক (ফোন নম্বর অনুযায়ী ২৪ ঘণ্টায় একবার)
        $phoneCacheKey = 'otp_sent_limit_' . $phone;
        if (cache()->has($phoneCacheKey)) {
            Log::warning("Limit Exceeded for phone: $phone", $locationData);
            return response()->json([
                'status' => 'failed',
                'message' => 'You can request an OTP only once every 24 hours.',
            ], 429);
        }

        // ৪. মেম্বার চেক
        $user = Member::where('phone', $phone)
                    ->select('id', 'phone', 'forgot', 'name') 
                    ->first();

        if (!$user) {
            Log::warning("Suspicious hit: User not found", $locationData);
            return response()->json([
                'status' => 'failed',
                'message' => 'If this number is registered, you will receive an OTP.',
            ], 404);
        }

        // ৫. OTP জেনারেট ও সেভ
        $otp = mt_rand(100000, 999999);
        $user->forgot = $otp;
        $user->save();

        try {
            $site_setting = GeneralSetting::where('status', 1)->first();
            $sms_gateway = SmsGateway::where('status', 1)->first();

            if ($sms_gateway) {
                $site_name = $site_setting->name ?? 'our service';
                $data = [
                    'api_key'  => $sms_gateway->api_key,
                    'number'   => $user->phone,
                    'type'     => 'text',
                    'senderid' => $sms_gateway->senderid,
                    'message'  => "Dear {$user->name},\r\nYour Forget verification code (OTP) is: {$otp}\nThank you!",
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $sms_gateway->url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_exec($ch);
                curl_close($ch);
            }

            // ৬. ওটিপি সফলভাবে পাঠানোর পর ২৪ ঘণ্টার জন্য লক করে দেওয়া
            cache()->put($phoneCacheKey, true, 300); // ৮৬৪০০ সেকেন্ড = ২৪ ঘণ্টা

            Log::info("OTP sent successfully", [
                'phone' => $user->phone,
                'location' => $locationData
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'OTP sent successfully',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Forgot password error: '.$e->getMessage(), $locationData);
            return response()->json(['status' => 'failed', 'message' => 'Failed to send OTP'], 500);
        }
    }


    public function forgot_verify(Request $request)
    {
        
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:12',
            'otp' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'type' => 'validation_error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Member::where('phone', $request->phone)->select('id', 'phone', 'password', 'forgot')->first();
       
        if (! $user) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Phone number not found',
            ], 404);
        }
         

        if ($user->forgot === null || (string)$user->forgot !== (string)$request->otp) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Invalid OTP',
            ], 401);
        }
        

        try {
            $user->forgot = null;
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Otp verified successfully',
            ], 200);

        } catch (Exception $e) {
            Log::error('Member reset password error: '.$e->getMessage());
            return response()->json([
                'status' => 'failed',
                'message' => 'Failed to Otp',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function new_password(Request $request)
    {
       
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:12',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'type' => 'validation_error',
                'errors' => $validator->errors(),
            ], 422);
        }
        
        $user = Member::where('phone', $request->phone)->select('id', 'phone', 'password')->first();
        
        if (! $user) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Phone number not found',
            ], 404);
        }
        
        try {
            $user->password = bcrypt($request->password);
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Password has been reset successfully',
            ], 200);

        } catch (Exception $e) {
            Log::error('Member reset password error: '.$e->getMessage());
            return response()->json([
                'status' => 'failed',
                'message' => 'Failed to reset password',
                'error' => $e->getMessage(),
            ], 500);
        }
        
        

    }


   public function change_password(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "old_password" => "required",
            "new_password" => "required",
            "confirm_password" =>
                "required_with:new_password|same:new_password|",
        ]);
        
     
        
        // $validator->validate([
        //     'old_password' => 'required',
        //     'new_password' => 'required',
        //      "confirm_password" =>
        //         "required_with:new_password|same:new_password|",
        // ]);
        
        if ($validator->fails()) {
            return response()->json(
                [
                    "status" => "validationfail",
                    "error" => "validation_error",
                    "message" => $validator->errors(),
                ],
                200
            );
        }
        $customer = Member::find(Auth::guard("member")->user()->id);
        $hashPass = $customer->password;

        if (Hash::check($request->old_password, $hashPass)) {
            $customer
                ->fill([
                    "password" => Hash::make($request->new_password),
                ])
                ->save();
            return response()->json([
                "status" => "success",
                "message" => "Password changed successfully!",
            ]);
        } else {
            return response()->json([
                "status" => "failed",
                "message" => "Old password not match!",
            ]);
        }
    }
    
    

    // public function pertnar_program(Request $request)
    // {
        
    //     $request->validate([
    //         'referrer_code' => 'required|string',
    //     ]);

    //     $memberId = Auth::guard('member')->id();
    //     $settings = PaymentChargeSetting::first();

    //     $minimum_limit   = $settings->partner_min_balance; 
    //     $first_gen_bonus = $settings->first_gen_bonus;
    //     $multi_gen_bonus = $settings->multi_gen_bonus;
    //     $partner_cost    = $settings->partner_own_bonus;

    //     $member = Member::find($memberId);

    //     if (!$member) {
    //         return response()->json(['error' => 'Unauthorized access.'], 401);
    //     }

    //     if (!is_null($member->referrer_id)) {
    //         return response()->json(['error' => 'You are already enrolled in the partner program.'], 400);
    //     }

    //     if ($member->username === $request->referrer_code) {
    //         return response()->json(['error' => 'You cannot use your own code as a referrer.'], 400);
    //     }

    //     if ($member->balance < $partner_cost) {
    //         return response()->json(['error' => 'Insufficient balance. You need ' . $partner_cost . ' to join.'], 400);
    //     }

    //     $referrer_member = Member::where('username', $request->referrer_code)->first();
    //     if (!$referrer_member) {
    //         return response()->json(['error' => 'Invalid referrer code. User not found.'], 404);
    //     }

        
    //     DB::beginTransaction();

    //     try {
            
    //         $member->update([
    //             'referrer_id' => $referrer_member->id,
    //             'start_date'  => now(),
    //             'expired_date'=> now()->addDays(365),
    //         ]);

    //         $member->decrement('balance', $partner_cost);
    //         $member->refresh(); 

    //         $join_tnx = 'PRT-' . strtoupper(Str::random(10));

            
    //         CustomerPayHistory::create([
    //             'member_id'    => $member->id,
    //             'payment_name' => 'Partner Program Joining Fee',
    //             'tnx'          => $join_tnx,
    //             'amount'       => $partner_cost,
    //             'balance'      => $member->balance, 
    //             'method'       => 'Wallet',
    //             'type'         => 'debit',
    //         ]);

            
    //         AdminPayHistory::create([
    //             'member_id'    => $member->id,
    //             'payment_name' => 'Partner Joining Fee from ' . $member->username,
    //             'tnx'          => $join_tnx,
    //             'amount'       => $partner_cost,
    //             'balance'      => $member->balance,
    //             'method'       => 'Wallet',
    //             'type'         => 'credit',
    //         ]);

            
    //         $currentReferrer = $referrer_member; 
    //         $level = 1;

            
    //         while ($currentReferrer && $level <= 100) {
    //             $amount = ($level === 1) ? $first_gen_bonus : $multi_gen_bonus;

    //             if ($amount > 0) {
    //                 $currentReferrer->increment('balance', $amount);
    //                 $currentReferrer->refresh();

    //                 $bonus_tnx = 'GEN' . $level . '-' . strtoupper(Str::random(10));

                    
    //                 CustomerPayHistory::create([
    //                     'member_id'    => $currentReferrer->id,
    //                     'payment_name' => "Generation Bonus (L-$level) from " . $member->username,
    //                     'tnx'          => $bonus_tnx,
    //                     'amount'       => $amount,
    //                     'balance'      => $currentReferrer->balance,
    //                     'method'       => 'Wallet',
    //                     'type'         => 'credit',
    //                 ]);

                   
    //                 AdminPayHistory::create([
    //                     'member_id'    => $currentReferrer->id,
    //                     'payment_name' => "Generation Bonus (L-$level) paid to " . $currentReferrer->username,
    //                     'tnx'          => $bonus_tnx,
    //                     'amount'       => $amount,
    //                     'balance'      => $currentReferrer->balance,
    //                     'method'       => 'Wallet',
    //                     'type'         => 'debit',
    //                 ]);
    //             }

                
    //             if ($currentReferrer->referrer_id) {
    //                 $currentReferrer = Member::find($currentReferrer->referrer_id);
    //             } else {
    //                 $currentReferrer = null; 
    //             }
                
    //             $level++;
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'status'  => 'success',
    //             'message' => 'Successfully joined the partner program and commissions distributed.',
    //         ]);

    //     } catch (\Exception $e) {
    //         // কোনো ভুল হলে সব আগের অবস্থায় ফিরে যাবে (Rollback)
    //         DB::rollBack();
    //         Log::error("Partner Joining Error: " . $e->getMessage());

    //         return response()->json([
    //             'error' => 'An error occurred during the process. Please try again later.'
    //         ], 500);
    //     }
    // }



    public function pertnar_program(Request $request)
    {
        $request->validate(['referrer_code' => 'nullable|string']);

        $memberId = Auth::guard('member')->id();
        $settings = PaymentChargeSetting::first();
        $partner_cost = $settings->partner_own_bonus;

        $member = Member::find($memberId);

        // ১. ভ্যালিডেশন
        if (!$member) return response()->json(['error' => 'Unauthorized access.'], 401);
        if ($member->verified != 1) {
            return response()->json(['error' => 'Your account must be verified to join.'], 400);
        }
        if ($member->partner == 1) return response()->json(['error' => 'Already enrolled.'], 400);
        if ($request->filled('referrer_code') && $member->username === $request->referrer_code) {
            return response()->json(['error' => 'Cannot use own code.'], 400);
        }
        if ($member->balance < $partner_cost) return response()->json(['error' => 'Insufficient balance.'], 400);

        // ২. রেফারার লজিক
        if ($request->filled('referrer_code')) {
            $referrer_member = Member::where('username', $request->referrer_code)->where('partner', 1)->where('verified', 1)->first();
            if (!$referrer_member) return response()->json(['error' => 'Invalid, unverified, or non-partner referrer.'], 404);
        } else {
            $referrer_member = Member::find(1); // Default Admin Referrer
        }

        DB::beginTransaction();
        try {
            // ৩. মেম্বার ডাটা আপডেট
            $member->update([
                'partner'     => 1,
                'referrer_id' => $referrer_member->id,
                'start_date'  => now(),
                'expired_date'=> now()->addDays(365),
            ]);

            $member->decrement('balance', $partner_cost);
            $member->refresh(); 
            $join_tnx = 'PRT-' . strtoupper(Str::random(10));

            // পেমেন্ট হিস্ট্রি (মেম্বার থেকে টাকা কাটলো)
            CustomerPayHistory::create([
                'member_id'    => $member->id,
                'payment_name' => 'Partner Program Joining Fee',
                'tnx'          => $join_tnx,
                'amount'       => $partner_cost,
                'balance'      => $member->balance, 
                'method'       => 'Wallet',
                'type'         => 'debit',
            ]);

            // অ্যাডমিন হিস্ট্রি (অ্যাডমিন জয়েনিং ফি পেলো)
            AdminPayHistory::create([
                'member_id'    => $member->id,
                'payment_name' => 'Partner Joining Fee from ' . $member->username,
                'tnx'          => $join_tnx,
                'amount'       => $partner_cost,
                'balance'      => $member->balance,
                'method'       => 'Wallet',
                'type'         => 'credit',
            ]);

            $this->sendFcmNotification(
                $member->id, 
                "Welcome! 🤝", 
                "You are now a verified partner.",
                [
                    'status' => 'partner_active',
                    'start_date' => now()->toDateString()
                ],
                'partner_enroll' // ৫ নম্বর প্যারামিটার (Type)
            );

            // ৪. কিউ (Queue) এ পাঠানো
            DistributePartnerBonus::dispatch($member, $referrer_member, $settings->first_gen_bonus, $settings->multi_gen_bonus);

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Successfully joined.']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Partner Joining Error: " . $e->getMessage());
            return response()->json(['error' => 'An error occurred.'], 500);
        }
    }































  
    // This is our old partnar programer code. 


//    public function pertnar_program(Request $request)
//     {
        
//         $request->validate([
//             'referrer_code' => 'required',
//         ]);
    
//         $memberId = Auth::guard('member')->id();

        
//         $member = Member::with('referrer')
//             ->select('id', 'name', 'username', 'balance', 'referrer_id', 'start_date', 'expired_date')
//             ->find($memberId);
    
//         if (!$member) {
//             return response()->json(['error' => 'Unauthorized'], 401);
//         }
    
//         if (!is_null($member->referrer_id)) {
//             return response()->json([
//                 'error' => 'You already have a partner.',
//             ], 400);
//         }
    
//         if ($member->username === $request->referrer_code) {
//             return response()->json([
//                 'error' => 'You cannot use your own username as referrer code.',
//             ], 400);
//         }
    
//         if ($member->balance < 2000) {
//             return response()->json([
//                 'error' => 'You must have at least 2000 balance to join the partner program.',
//             ], 400);
//         }
    

        
//          // default referrer id
//         $referrer_id = 1;
    
//         if ($request->filled('referrer_code')) {
//             $referrer_member = Member::where('username', $request->referrer_code)
//                 ->select('id', 'name', 'username', 'balance', 'referrer_id')
//                 ->first();
    
//             if (!$referrer_member) {
//                 return response()->json(['error' => 'Invalid referrer code.'], 404);
//             }
    
//             $referrer_id = $referrer_member->id;
//         }
    
//         $member->update([
//             'referrer_id' => $referrer_member->id,
//             'start_date' => now(),
//             'expired_date' => now()->addDays(365),
//         ]);
    
//         $member->decrement('balance', 1900);
    
//         $commissionRates = [
//             1 => 400, 
//         ];
//         for ($i = 2; $i <= 100; $i++) {
//             $commissionRates[$i] = 50; 
//         }
    
//         $currentReferrer = $referrer_member; 
//         $level = 1;
    
//         while ($currentReferrer && $level <= 100) {
//             if (isset($commissionRates[$level])) {
//                 $currentReferrer->increment('balance', $commissionRates[$level]);
//             }
    
//             $currentReferrer = $currentReferrer->referrer;
//             $level++;
//         }
//         return response()->json([
//             'status' => 'success',
//             'message' => 'Partner program joined successfully!',
//             'member_id' => $member->id,
//             'referrer_id' => $referrer_member->id,
//         ]);
//     }
    
    // public function monetization(Request $request)
    // {
    //     $memberId = Auth::guard('member')->user()->id;
    
    //     $member = Member::select(
    //         'id', 'name', 'username', 'phone', 'balance', 
    //         'phoneverify', 'approved', 'verified', 'status', 'monetization'
    //     )->find($memberId);
    
    //     if (!$member) {
    //         return response()->json(['error' => 'Member not found'], 404);
    //     }
    
    //     if ($member->phoneverify != 1) {
    //         return response()->json([
    //             'message' => 'Phone is not verified'
    //         ]);
    //     }
    
    //     if ($member->approved != 1) {
    //         return response()->json([
    //             'message' => 'Your account is not approved yet'
    //         ]);
    //     }
    
    //     if ($member->verified != 1) {
    //         return response()->json([
    //             'message' => 'Account verification is pending'
    //         ]);
    //     }
    
    //     if ($member->status != 1) {
    //         return response()->json([
    //             'message' => 'Account is not active'
    //         ]);
    //     }
    
    //     if ($member->balance >= 2000) {
    //         $member->balance -= 2000;
    //         $member->monetization = 1;
    //         $member->save();
    
    //         return response()->json([
    //             'message' => 'Balance deducted and monetization activated',
    //             'balance' => $member->balance,
    //             'monetization' => $member->monetization
    //         ]);
    //     } else {
    //         return response()->json([
    //             'message' => 'Insufficient balance for monetization'
    //         ]);
    //     }
    // }
    
    
    
   public function phone_verify(Request $request)
    {
        $request->validate([
            'phone' => 'required',
            'otp'   => 'required',
        ]);
    
        $member = Member::where('phone', $request->phone)->select('id','name','phone','username','phoneverify','approved', 'status')->first();
    
        if (!$member) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Member not found'
            ], 404);
        }
    
        if ($member->phoneverify == $request->otp) {
            $member->update([
                'approved' => 1,
                'phoneverify' => 1, 
                'status' => 1
            ]);
    
            return response()->json([
                'status' => 'success',
                'message' => 'Account activated successfully',
                'data' => $member
            ], 200);
        }
    
        return response()->json([
            'status' => 'failed',
            'message' => 'Verification code does not match'
        ], 400);
    }



    // public function profile(Request $request)
    // {
    //     $authMember = Auth::guard('member')->user();
    //     $authId = $authMember ? $authMember->id : null;
        
    //     $memberId = $request->id;
        
    //         if(!$memberId){
    //             return response()->json([
    //             'status' => 'failed',
    //             'message'=> 'No member id found',
    //         ]); 
    //         }
            
    //      $miniAds = Miniad::where('status', 1)
    //         ->select('id', 'title', 'image', 'link')
    //         ->get();
    
    //     if ($miniAds->isEmpty()) {
    //         $miniAds = collect([]);
    //     }
            
    //     $member = Member::where(['id' =>$request->id])->first();
    //     // return $member;

    //     if ($authId && $authId != $memberId) {
    //         $checkFollow = Follow::where('follower_id', $authId)
    //                             ->where('following_id', $memberId)
    //                             ->exists();
    //         $member->is_following = $checkFollow; // ফলো করলে true, না করলে false
    //     } else {
    //         $member->is_following = false; // নিজের প্রোফাইল হলে বা লগইন না থাকলে false
    //     }
        
        
    //      $district = DB::table('districts')
    //         ->where('id', $member->district)
    //         ->select('id', 'name')
    //         ->first();
    
    //     $upazila = DB::table('upazilas')
    //         ->where('id', $member->upazila)
    //         ->select('id', 'name')
    //         ->first();
            
    //     $profession = DB::table('professions')
    //         ->where('id', $member->profession)
    //         ->select('id', 'title')
    //         ->first();
            
    //     $division = DB::table('divisions')
    //         ->where('id', $member->division)
    //         ->select('id', 'name')
    //         ->first();
        
    //     $nationality = DB::table('countries')
    //         ->where('id', $member->nationality)
    //         ->select('id', 'name')
    //         ->first();
    
    //     $member->nationality = $nationality;
    //     $member->district = $district;
    //     $member->upazila  = $upazila;
    //     $member->profession  = $profession;
    //     $member->division = $division;
    //     $followers_count = Follow::where('following_id', $member->id)->count();

    //     $following_count = Follow::where('follower_id', $member->id)->count();

    //     $friends_count = Follow::where('follower_id', $member->id)
    //                         ->where('is_friend', 1)
    //                         ->count();
        
        
        
    //     $posts = Post::with(['member', 'media'])
    //         ->withCount([
    //             'likes as like_count' => function ($q) {
    //                 $q->where('type', 1);
    //             },
    //             'likes as dislike_count' => function ($q) {
    //                 $q->where('type', 2);
    //             },
    //             'comments as comment_count' 
    //         ])
    //         ->withExists([
    //             'likes as liked_by_me' => function ($q) use ($memberId) {
    //                 $q->where('member_id', $memberId)->where('type', 1);
    //             },
    //             'likes as disliked_by_me' => function ($q) use ($memberId) {
    //                 $q->where('member_id', $memberId)->where('type', 2);
    //             },
    //         ])
    //         ->where('member_id', $memberId)
    //         ->where('status', 'active')
    //         // ->whereNotIn('id', function ($query) use ($memberId) {
    //         //     $query->select('post_id')
    //         //           ->from('post_views')
    //         //           ->where('member_id', $memberId);
    //         // })
    //         ->latest()
    //         ->paginate(5);
            
            
    //         $posts->getCollection()->transform(function ($post, $index) use ($memberId, $miniAds) {
    //         $isFollowing = Follow::where('follower_id', $memberId)
    //             ->where('following_id', $post->member->id)
    //             ->exists();
    
    //         $post->is_following = $isFollowing;
            
            
    //         $followBoost = FollowBoost::where('member_id', $post->member->id)->first();
    
    //         if ($followBoost && $followBoost->status === 'active') {
    //             $post->follow_boost_status = 'active';
    //         } else {
    //             $post->follow_boost_status = 'inactive';
    //         }
            
    //         // PostBoost check
    //         $postBoost = PostBoost::where('post_id', $post->id)->latest()->first();
        
    //         if ($postBoost && $postBoost->status === 'active') {
    //             $post->post_boost = [
    //                 'id' => $postBoost->id,
    //                 'status' => 'active'
    //             ];
    //         } else {
    //             $post->post_boost = [
    //                 'id' => null,
    //                 'status' => 'inactive'
    //             ];
    //         }
    
    //         // if ($miniAds->count() > 0) {
                
    //         //     $start = ($index * 2) % $miniAds->count();
    //         //     $miniAdPair = [];
    
    //         //     for ($i = 0; $i < 2; $i++) {
    //         //         $miniAdPair[] = $miniAds[($start + $i) % $miniAds->count()];
    //         //     }
    
    //         //     $post->mini_ads = $miniAdPair;
    //         // } else {
    //         //     $post->mini_ads = [];
    //         // }

            
    //         if ($miniAds->count() > 0) {
    //             $adIndex = $index % $miniAds->count();
                
    //             $post->mini_ads = $miniAds[$adIndex]; 
    //         } else {
    //             $post->mini_ads = null; 
    //         }
    
    //         return $post;
    //     });
            
            
            
            
        
            
    //     return response()->json([
    //         'status' => 'success',
    //         'message'=> '10 post show',
    //         'followers_count' => $followers_count, 
    //         'following_count' => $following_count, 
    //         'friends_count'   => $friends_count,
    //         "is_following" => $member->is_following,
    //         'member'   => $member,
    //         'data'   => $posts,
            
    //     ]);
        
    // }


    // public function profile(Request $request)
    // {
    //     // ১. লগইন মেম্বার চেক (থাকলে ID পাবে, না থাকলে null)
    //     $authMember = Auth::guard('member')->user();
    //     $authId = $authMember ? $authMember->id : null;
        
    //     $memberId = $request->id;
        
    //     if(!$memberId){
    //         return response()->json(['status' => 'failed', 'message'=> 'No member id found'], 400); 
    //     }
                
    //     // ২. মেম্বার ডাটা লোড (Eager Loading ব্যবহার করা ভালো ছিল, তবে আপনার স্টাইল অনুযায়ী রাখা হলো)
    //     $member = Member::find($memberId);

    //     if (!$member) {
    //         return response()->json(['status' => 'failed', 'message'=> 'Member not found'], 404);
    //     }

    //     // ৩. ফলো স্ট্যাটাস (গেস্ট হলে সবসময় false)
    //     if ($authId && $authId != $memberId) {
    //         $member->is_following = Follow::where('follower_id', $authId)
    //                                     ->where('following_id', $memberId)
    //                                     ->exists();
    //     } else {
    //         $member->is_following = false;
    //     }
        
    //     // ৪. অ্যাড্রেস এবং প্রফেশন লোড (ডিবি কুয়েরি কমানোর জন্য আপনি মডেলে রিলেশন করতে পারেন)
    //     $member->nationality = DB::table('countries')->where('id', $member->nationality)->select('id', 'name')->first();
    //     $member->district = DB::table('districts')->where('id', $member->district)->select('id', 'name')->first();
    //     $member->upazila  = DB::table('upazilas')->where('id', $member->upazila)->select('id', 'name')->first();
    //     $member->profession  = DB::table('professions')->where('id', $member->profession)->select('id', 'title')->first();
    //     $member->division = DB::table('divisions')->where('id', $member->division)->select('id', 'name')->first();

    //     // ৫. কাউন্টগুলো বের করা
    //     $followers_count = Follow::where('following_id', $memberId)->count();
    //     $following_count = Follow::where('follower_id', $memberId)->count();
    //     $friends_count = Follow::where('follower_id', $memberId)->where('is_friend', 1)->count();
        
    //     // ৬. মিনি অ্যাডস ক্যাশ থেকে বা সরাসরি নেওয়া
    //     $miniAds = Miniad::where('status', 1)->select('id', 'title', 'image', 'link')->get();

    //     // ৭. পোস্ট কুয়েরি (TikTok স্টাইল গেস্ট লজিক)
    //     $posts = Post::with(['member:id,name,image', 'media'])
    //         ->withCount([
    //             'likes as like_count' => fn($q) => $q->where('type', 1),
    //             'likes as dislike_count' => fn($q) => $q->where('type', 2),
    //             'comments as comment_count' 
    //         ])
    //         ->withExists([
    //             // লগইন থাকলে অরিজিনাল ডাটা, না থাকলে FALSE
    //             'likes as liked_by_me' => fn($q) => $authId ? $q->where('member_id', $authId)->where('type', 1) : $q->whereRaw('1=0'),
    //             'likes as disliked_by_me' => fn($q) => $authId ? $q->where('member_id', $authId)->where('type', 2) : $q->whereRaw('1=0'),
    //         ])
    //         ->where('member_id', $memberId)
    //         ->where('status', 'active')
    //         ->latest()
    //         ->paginate(5);
                
    //     // ৮. ট্রান্সফর্ম লজিক
    //     $posts->getCollection()->transform(function ($post, $index) use ($authId, $miniAds) {
    //         // ফলো বুস্ট চেক
    //         $followBoost = FollowBoost::where('member_id', $post->member->id)->where('status', 'active')->exists();
    //         $post->follow_boost_status = $followBoost ? 'active' : 'inactive';
            
    //         // পোস্ট বুস্ট চেক
    //         $postBoost = PostBoost::where('post_id', $post->id)->where('status', 'active')->first();
    //         $post->post_boost = $postBoost ? ['id' => $postBoost->id, 'status' => 'active'] : ['id' => null, 'status' => 'inactive'];

    //         // গেস্ট হলে পোস্টের জন্য is_following সবসময় false
    //         $post->is_following = $authId ? Follow::where('follower_id', $authId)->where('following_id', $post->member->id)->exists() : false;

    //         // অ্যাডস ইনজেকশন
    //         $post->mini_ads = $miniAds->count() > 0 ? $miniAds[$index % $miniAds->count()] : null;

    //         return $post;
    //     });

    //     return response()->json([
    //         'status' => 'success',
    //         'followers_count' => $followers_count, 
    //         'following_count' => $following_count, 
    //         'friends_count'   => $friends_count,
    //         'is_following'    => $member->is_following,
    //         'member'          => $member,
    //         'data'            => $posts,
    //     ]);
    // }

    // public function profile(Request $request)
    // {
    //     $authMember = Auth::guard('member')->user();
    //     $authId = $authMember ? $authMember->id : null;
    //     $memberId = $request->id;

    //     if (!$memberId) {
    //         return response()->json(['status' => 'failed', 'message' => 'No member id found'], 400);
    //     }

    //     // ১. মেম্বার ডাটা এবং অ্যাডস ক্যাশ করা (প্রোফাইল লোড ফাস্ট হবে)
    //     $member = Cache::remember("member_profile_{$memberId}", 300, function () use ($memberId) {
    //         $m = Member::find($memberId);
    //         if ($m) {
    //             // ডাটাবেস কুয়েরি কমাতে রিলেশনগুলো একবারেই লোড করা (Join এর বিকল্প)
    //             $m->nationality = DB::table('countries')->where('id', $m->nationality)->select('id', 'name')->first();
    //             $m->district = DB::table('districts')->where('id', $m->district)->select('id', 'name')->first();
    //             $m->upazila = DB::table('upazilas')->where('id', $m->upazila)->select('id', 'name')->first();
    //             $m->profession = DB::table('professions')->where('id', $m->profession)->select('id', 'title')->first();
    //             $m->division = DB::table('divisions')->where('id', $m->division)->select('id', 'name')->first();
    //         }
    //         return $m;
    //     });

    //     if (!$member) {
    //         return response()->json(['status' => 'failed', 'message' => 'Member not found'], 404);
    //     }

    //     // ২. ফলো চেক (এটি ক্যাশ করা যাবে না কারণ এটি প্রতি ইউজারের জন্য আলাদা)
    //     $member->is_following = ($authId && $authId != $memberId) 
    //         ? Follow::where('follower_id', $authId)->where('following_id', $memberId)->exists() 
    //         : false;

    //     // ৩. কাউন্ট গুলো আলাদাভাবে ক্যাশ করা (খুবই কার্যকর যখন অনেক ফলোয়ার থাকে)
    //     $counts = Cache::remember("member_counts_{$memberId}", 60, function () use ($memberId) {
    //         return [
    //             'followers' => Follow::where('following_id', $memberId)->count(),
    //             'following' => Follow::where('follower_id', $memberId)->count(),
    //             'friends'   => Follow::where('follower_id', $memberId)->where('is_friend', 1)->count(),
    //         ];
    //     });

    //     $miniAds = Cache::remember('active_mini_ads', 600, function () {
    //         return Miniad::where('status', 1)->select('id', 'title', 'image', 'link')->get();
    //     });

    //     // ৪. পোস্ট কুয়েরি অপ্টিমাইজেশন (Eager Loading ব্যবহার করে N+1 কোয়েরি বন্ধ করা)
    //     $posts = Post::with(['member:id,name,image', 'media'])
    //         ->withCount([
    //             'likes as like_count' => fn($q) => $q->where('type', 1),
    //             'likes as dislike_count' => fn($q) => $q->where('type', 2),
    //             'comments as comment_count'
    //         ])
    //         ->withExists([
    //             'likes as liked_by_me' => fn($q) => $authId ? $q->where('member_id', $authId)->where('type', 1) : $q->whereRaw('1=0'),
    //             'likes as disliked_by_me' => fn($q) => $authId ? $q->where('member_id', $authId)->where('type', 2) : $q->whereRaw('1=0'),
    //         ])
    //         ->where('member_id', $memberId)
    //         ->where('status', 'active')
    //         ->latest()
    //         ->paginate(5);

    //     // ৫. লুপের ভেতরে কুয়েরি কমানো (খুবই গুরুত্বপূর্ণ স্কেলেবিলিটির জন্য)
    //     $posts->getCollection()->transform(function ($post, $index) use ($authId, $miniAds) {
    //         // বুস্ট স্ট্যাটাস চেক (এখানে রিলেশন থাকলে আরও ফাস্ট হতো)
    //         $followBoost = FollowBoost::where('member_id', $post->member_id)->where('status', 'active')->exists();
    //         $post->follow_boost_status = $followBoost ? 'active' : 'inactive';

    //         $postBoost = PostBoost::where('post_id', $post->id)->where('status', 'active')->first();
    //         $post->post_boost = $postBoost ? ['id' => $postBoost->id, 'status' => 'active'] : ['id' => null, 'status' => 'inactive'];

    //         $post->is_following = $authId ? Follow::where('follower_id', $authId)->where('following_id', $post->member_id)->exists() : false;
    //         $post->mini_ads = $miniAds->count() > 0 ? $miniAds[$index % $miniAds->count()] : null;

    //         return $post;
    //     });

    //     return response()->json([
    //         'status' => 'success',
    //         'followers_count' => $counts['followers'],
    //         'following_count' => $counts['following'],
    //         'friends_count'   => $counts['friends'],
    //         'is_following'    => $member->is_following,
    //         'member'          => $member,
    //         'data'            => $posts,
    //     ]);
    // }

    public function profile(Request $request)
    {
        $authMember = Auth::guard('member')->user();
        $authId = $authMember ? $authMember->id : null;
        $memberId = $request->id;

        if (!$memberId) {
            return response()->json(['status' => 'failed', 'message' => 'No member id found'], 400);
        }

        // ১. মেম্বার ডাটা এবং অ্যাডস ক্যাশ করা (প্রোফাইল লোড ফাস্ট হবে)
        $member = Cache::remember("member_profile_{$memberId}", 300, function () use ($memberId) {
            $m = Member::find($memberId);
            if ($m) {
                // ডাটাবেস কুয়েরি কমাতে রিলেশনগুলো একবারেই লোড করা (Join এর বিকল্প)
                $m->nationality = DB::table('countries')->where('id', $m->nationality)->select('id', 'name')->first();
                $m->district = DB::table('districts')->where('id', $m->district)->select('id', 'name')->first();
                $m->upazila = DB::table('upazilas')->where('id', $m->upazila)->select('id', 'name')->first();
                $m->profession = DB::table('professions')->where('id', $m->profession)->select('id', 'title')->first();
                $m->division = DB::table('divisions')->where('id', $m->division)->select('id', 'name')->first();
            }
            return $m;
        });

        if (!$member) {
            return response()->json(['status' => 'failed', 'message' => 'Member not found'], 404);
        }

        // ২. ফলো চেক (এটি ক্যাশ করা যাবে না কারণ এটি প্রতি ইউজারের জন্য আলাদা)
        $member->is_following = ($authId && $authId != $memberId) 
            ? Follow::where('follower_id', $authId)->where('following_id', $memberId)->exists() 
            : false;

        // ৩. কাউন্ট গুলো আলাদাভাবে ক্যাশ করা (খুবই কার্যকর যখন অনেক ফলোয়ার থাকে)
        $counts = Cache::remember("member_counts_{$memberId}", 60, function () use ($memberId) {
            return [
                'followers' => Follow::where('following_id', $memberId)->count(),
                'following' => Follow::where('follower_id', $memberId)->count(),
                'friends'   => Follow::where('follower_id', $memberId)->where('is_friend', 1)->count(),
            ];
        });

        $miniAds = Cache::remember('active_mini_ads', 600, function () {
            return Miniad::where('status', 1)->select('id', 'title', 'image', 'link')->get();
        });

        // ৪. পোস্ট কুয়েরি অপ্টিমাইজেশন (Eager Loading ব্যবহার করে N+1 কোয়েরি বন্ধ করা)
        $posts = Post::with(['member:id,name,image', 'media'])
            ->withCount([
                'likes as like_count' => fn($q) => $q->where('type', 1),
                'likes as dislike_count' => fn($q) => $q->where('type', 2),
                'comments as comment_count'
            ])
            ->withExists([
                'likes as liked_by_me' => fn($q) => $authId ? $q->where('member_id', $authId)->where('type', 1) : $q->whereRaw('1=0'),
                'likes as disliked_by_me' => fn($q) => $authId ? $q->where('member_id', $authId)->where('type', 2) : $q->whereRaw('1=0'),
            ])
            ->where('member_id', $memberId)
            ->where('status', 'active')
            ->latest()
            ->paginate(5);

        // ৫. লুপের ভেতরে কুয়েরি কমানো (খুবই গুরুত্বপূর্ণ স্কেলেবিলিটির জন্য)
        $posts->getCollection()->transform(function ($post, $index) use ($authId, $miniAds) {
            // বুস্ট স্ট্যাটাস চেক (এখানে রিলেশন থাকলে আরও ফাস্ট হতো)
            $followBoost = FollowBoost::where('member_id', $post->member_id)->where('status', 'active')->exists();
            $post->follow_boost_status = $followBoost ? 'active' : 'inactive';

            $postBoost = PostBoost::where('post_id', $post->id)->where('status', 'active')->first();
            $post->post_boost = $postBoost ? ['id' => $postBoost->id, 'status' => 'active'] : ['id' => null, 'status' => 'inactive'];

            $post->is_following = $authId ? Follow::where('follower_id', $authId)->where('following_id', $post->member_id)->exists() : false;
            $post->mini_ads = $miniAds->count() > 0 ? $miniAds[$index % $miniAds->count()] : null;

            return $post;
        });

        return response()->json([
            'status' => 'success',
            'followers_count' => $counts['followers'],
            'following_count' => $counts['following'],
            'friends_count'   => $counts['friends'],
            'is_following'    => $member->is_following,
            'member'          => $member,
            'data'            => $posts,
        ]);
    }


    public function approved_acount($id)
    {
        $member = Member::find($id);
       
        if (!$member) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Member not found',
                'data'    => null
            ]);
        }
        
        if ($member->balance >= 2000) {
            $member->decrement('balance', 1900);
            $member->approved     = 1;
            $member->start_date   = now();
            $member->expired_date = now()->addDays(365);
            $member->save();

            $commissionRates = [
                1 => 400,
            ];

            for ($i = 2; $i <= 100; $i++) {
                $commissionRates[$i] = 10;
            }

            $currentReferrer = $member->referrer; 
            $level = 1;

            while ($currentReferrer && $level <= 100) {
                if (isset($commissionRates[$level])) {
                    $currentReferrer->increment('balance', $commissionRates[$level]);
                }
                $currentReferrer = $currentReferrer->referrer; 
                $level++;
            }

        }

         else {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Insufficient balance to activate account',
                'data'    => null
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Account activated successfully!',
            'data'    => $member
        ]);
    }


    public function my_profile(Request $request)
    {
        $member = Auth::guard("member")->user();
    
        if (!$member) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Member not found'
            ], 404);
        }
    
        $district = DB::table('districts')
            ->where('id', $member->district)
            ->select('id', 'name')
            ->first();
    
        $upazila = DB::table('upazilas')
            ->where('id', $member->upazila)
            ->select('id', 'name')
            ->first();
            
        $profession = DB::table('professions')
            ->where('id', $member->profession)
            ->select('id', 'title')
            ->first();
            
        $division = DB::table('divisions')
            ->where('id', $member->division)
            ->select('id', 'name')
            ->first();

        $nationality = DB::table('countries')
            ->where('id', $member->nationality)
            ->select('id', 'name')
            ->first();
    
        $member->district = $district;
        $member->nationality = $nationality;
        $member->upazila  = $upazila;
        $member->profession  = $profession;
        $member->division = $division;

        $followers_count = Follow::where('following_id', $member->id)->count();

        $following_count = Follow::where('follower_id', $member->id)->count();

        $friends_count = Follow::where('follower_id', $member->id)
                            ->where('is_friend', 1)
                            ->count();
        
        $boost = FollowBoost::where('member_id', $member->id)
            ->where('status', 'active')
            ->first();
        
        if ($boost) {
            $boost_status = 'active';
        } else {
            $boost_status = 'inactive';
        }
    
    
        return response()->json([
            'status' => 'success',
            'message'=> 'Your Profile Details',
            'follow_boost' => $boost_status,'followers_count' => $followers_count, 
            'following_count' => $following_count, 
            'friends_count'   => $friends_count,
            'data'   => $member,
        ], 200);
    }

    

    public function allteam(Request $request)
    {
       $member = Auth::guard("member")->user();
       if (!$member) {
        return response()->json([
            'status' => failed,
                'message' => 'Unauthorized user'
            ], 401);
        }
        
        $perPage = request()->query('per_page', 20);
        $referrals = $member->allReferrals()->paginate($perPage);
        
        return response()->json([
            'status' => 'success',
            'member' => $member,
            'referrals' => $referrals
        ]);
    }
    
    
    public function verifywithDocument(Request $request)
    {
        try {
            $member = Auth::guard('member')->user();

            if (!$member) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $type = $request->type;

            // --- ১. ভ্যালিডেশন (২০ এমবি পর্যন্ত অনুমতি দেওয়া হয়েছে) ---
            $rules = [
                'type'      => 'required|in:nid,birth,passport,driving',
                'country'   => 'required',
                'city'      => 'required',
            ];

            if ($type === 'nid') {
                $rules += [
                    'nid_number'      => 'required',
                    'nid_front_image' => 'required|image|mimes:jpg,png,webp,jpeg|max:20480',
                    'nid_back_image'  => 'required|image|mimes:jpg,png,webp,jpeg|max:20480',
                ];
            } elseif ($type === 'birth') {
                $rules += [
                    'birth_number' => 'required',
                    'birth_image'  => 'required|image|mimes:jpg,png,webp,jpeg|max:20480',
                ];
            } elseif ($type === 'passport') {
                $rules += [
                    'passport_image' => 'required|image|mimes:jpg,png,webp,jpeg|max:20480',
                ];
            } elseif ($type === 'driving') {
                $rules += [
                    'driving_front_image' => 'required|image|mimes:jpg,png,webp,jpeg|max:20480',
                    'driving_back_image'  => 'required|image|mimes:jpg,png,webp,jpeg|max:20480',
                ];
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // মেম্বার বেসিক ডাটা আপডেট
            $member->update([
                'country'   => $request->country,
                'city'      => $request->city,
            ]);

            $data = [
                'member_id'    => $member->id,
                'type'         => $type,
                'nid_number'   => $request->nid_number,
                'birth_number' => $request->birth_number,
            ];

            // --- ২. GCS ও ইমেজ প্রসেসিং সেটআপ ---
            $bucketName = config('filesystems.disks.gcs.bucket');
            $keyFileData = config('filesystems.disks.gcs.key_file');
            if (!is_array($keyFileData)) {
                $keyFileData = json_decode(file_get_contents(base_path($keyFileData)), true);
            }
            
            $storage = new \Google\Cloud\Storage\StorageClient([
                'projectId' => config('filesystems.disks.gcs.project_id'),
                'keyFile'   => $keyFileData,
            ]);
            $bucket = $storage->bucket($bucketName);

            $fields = [
                'nid_front_image', 'nid_back_image', 'birth_image', 
                'identity_image', 'salfy_image', 'passport_image', 
                'driving_front_image', 'driving_back_image'
            ];

            foreach ($fields as $field) {
                if ($request->hasFile($field)) {
                    $image = $request->file($field);
                    $fileName = 'member_verify/' . time() . '-' . uniqid() . '.webp';

                    // ইমেজ ইন্টারভেনশন শুরু (ডকুমেন্ট হিসেবে ক্লিয়ার রাখতে ৮০০px উইডথ)
                    $img = Image::make($image->getRealPath())->orientate();
                    
                    // ডাইমেনশন কমানো যাতে সাইজ দ্রুত কমে
                    $img->resize(800, null, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });

                    // WebP এনকোডিং (প্রথমে ৮০% কোয়ালিটি)
                    $encodedImage = $img->encode('webp', 80);

                    // যদি সাইজ ৫০০kb এর বেশি হয়, কোয়ালিটি আরও কমানো
                    if (strlen($encodedImage->getEncoded()) > 500 * 1024) {
                        $encodedImage = $img->encode('webp', 65);
                    }

                    // GCS আপলোড
                    $object = $bucket->upload($encodedImage->getEncoded(), [
                        'name' => $fileName,
                        'metadata' => [
                            'contentType' => 'image/webp'
                        ]
                    ]);

                    if ($object) {
                        $data[$field] = "https://storage.googleapis.com/" . $bucketName . "/" . $fileName;
                    }
                }
            }

            // ভেরিফিকেশন রেকর্ড তৈরি
            MemberVerify::create($data);
            
            $member->verified = 0; // ভেরিফিকেশন পেন্ডিং
            $member->submit = 1; // রিভিউ প্রক্রিয়া শুরু
            $member->save();

            return response()->json([
                'status'  => 'success',
                'message' => 'Verification submitted successfully!',
                'data'    => $data,
            ], 200);

        } catch (\Exception $e) {
            \Log::error("Member Verification Error: " . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }







    // public function verifywithDocument(Request $request)
    // {
    //     $member = Auth::guard('member')->user();
    
    //     if (!$member) {
    //         return response()->json(['error' => 'Unauthorized'], 401);
    //     }
    
    //     $type = $request->type;
    
    //     $rules = [
    //         'type' => 'required|in:nid,birth,passport,driving',
    
    //         'country'       => 'required',
    //         'post_code'     => 'required',
    //         'city'          => 'required',
    //     ];
    
    //     if ($type === 'nid') {
    //         $rules += [
    //             'nid_number' => 'required',
    //             'nid_front_image' => 'required|image|mimes:jpg,png,webp,jpeg|max:2048',
    //             'nid_back_image'  => 'required|image|mimes:jpg,png,webp,jpeg|max:2048',
    //         ];
    //     } elseif ($type === 'birth') {
    //         $rules += [
    //             'birth_number' => 'required',
    //             'birth_image' => 'required|image|mimes:jpg,png,webp,jpeg|max:2048',
    //         ];
    //     } elseif ($type === 'passport') {
    //         $rules += [
    //             'passport_image' => 'required|image|mimes:jpg,png,webp,jpeg|max:2048',
    //         ];
    //     } elseif ($type === 'driving') {
    //         $rules += [
    //             'driving_front_image' => 'required|image|mimes:jpg,png,webp,jpeg|max:2048',
    //             'driving_back_image' => 'required|image|mimes:jpg,png,webp,jpeg|max:2048',
    //         ];
    //     }
    
    //     $validator = Validator::make($request->all(), $rules);
    
    //     if ($validator->fails()) {
    //         return response()->json(['errors' => $validator->errors()], 422);
    //     }
    
    //     $member->update([
    //         'country'       => $request->country,
    //         'post_code'     => $request->post_code,
    //         'city'          => $request->city,
    //     ]);
    
    //     $data = [
    //         'member_id' => $member->id,
    //         'type' => $type,
    //         'nid_number' => $request->nid_number,
    //         'birth_number' => $request->birth_number,
    //     ];
    
    //     $uploadPath = 'public/uploads/member_verify/';
    //     $fields = [
    //         'nid_front_image',
    //         'nid_back_image',
    //         'birth_image',
    //         'identity_image',
    //         'salfy_image',
    //         'passport_image',
    //         'driving_front_image',
    //         'driving_back_image'
    //     ];
    
    //     foreach ($fields as $field) {
    //         if ($request->hasFile($field)) {
    //             $image = $request->file($field);
    //             $name = time().'-'.uniqid().'.webp';
    //             $imageUrl = $uploadPath.$name;
    
    //             Image::make($image->getRealPath())
    //                 ->encode('webp', 80)
    //                 ->save($imageUrl);
    
    //             $data[$field] = str_replace('public/', '', $imageUrl);
    //         }
    //     }
    
    //     MemberVerify::create($data);
    //     $member->verified = 0;
    //     $member->save();
    
    //     return response()->json([
    //         'message' => 'Verification submitted successfully!',
    //         'data' => $data,
    //         'member' => $member
    //     ], 200);
    // }

   
    
    
    
    
    // public function verifywithDocument(Request $request)
    // {
    //     $member = Auth::guard('member')->user();
    
    //     if (!$member) {
    //         return response()->json(['error' => 'Unauthorized'], 401);
    //     }
        
    //     $type = $request->type;
    
    //     $rules = [
    //         'type' => 'required|in:nid,birth,passport,driving',
    //     ];
    
    //     if ($type === 'nid') {
    //         $rules += [
    //             'nid_number' => 'required',
    //             'nid_front_image' => 'required|image|mimes:jpg,png,webp,jpeg|max:2048',
    //             'nid_back_image' => 'required|image|mimes:jpg,png,webp,jpeg|max:2048',
    //         ];
    //     } elseif ($type === 'birth') {
    //         $rules += [
    //             'birth_number' => 'required',
    //             'birth_image' => 'required|image|mimes:jpg,png,webp,jpeg|max:2048',
    //         ];
    //     } elseif ($type === 'passport') {
    //         $rules += [
    //             'passport_image' => 'required|image|mimes:jpg,png,webp,jpeg|max:2048',
    //         ];
    //     } elseif ($type === 'driving') {
    //         $rules += [
    //             'driving_front_image' => 'required|image|mimes:jpg,png,webp,jpeg|max:2048',
    //             'driving_back_image' => 'required|image|mimes:jpg,png,webp,jpeg|max:2048',
    //         ];
    //     }
    
    //     $validator = Validator::make($request->all(), $rules);
    
    //     if ($validator->fails()) {
    //         return response()->json(['errors' => $validator->errors()], 422);
    //     }
    
    //     $data = [
    //         'member_id' => $member->id,
    //         'type' => $type,
    //         'nid_number' => $request->nid_number,
    //         'birth_number' => $request->birth_number,
    //     ];
    
    //     $uploadPath = 'public/uploads/member_verify/';
    //     $fields = [
    //         'nid_front_image',
    //         'nid_back_image',
    //         'birth_image',
    //         'passport_image',
    //         'driving_front_image',
    //         'driving_back_image'
    //     ];
        
    //     foreach ($fields as $field) {
    //         if ($request->hasFile($field)) {
    //             $image = $request->file($field);
    //             $name = time() . '-' . $image->getClientOriginalName();
    //             $name = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp', $name);
    //             $name = strtolower(preg_replace('/\s+/', '-', $name));
    //             $imageUrl = $uploadPath . $name;
        
    //             $targetWidth = 600;
    //             $img = Image::make($image->getRealPath());
    //             $originalWidth = $img->width();
    //             $originalHeight = $img->height();
    //             $ratio = $originalHeight / $originalWidth;
    //             $targetHeight = intval($targetWidth * $ratio);
        
    //             $img->resize($targetWidth, null, function ($constraint) {
    //                 $constraint->aspectRatio();
    //                 $constraint->upsize();
    //             });
        
    //             $img->resizeCanvas($targetWidth, $targetHeight, 'center', false, '#ffffff');
        
    //             $quality = 90;
    //             do {
    //                 $tempPath = $uploadPath . 'temp_' . $name;
    //                 $img->encode('webp', $quality)->save($tempPath);
    //                 $size = filesize($tempPath) / 1024 / 1024;
    //                 $quality -= 5;
    //             } while ($size > 2 && $quality >= 10);
        
               
    //             rename($tempPath, $imageUrl);
        
            
    //             $data[$field] = str_replace('public/', '', $imageUrl);
    //         }
    //     }
    
        
    //     MemberVerify::create($data);
        
    //      $requiredFields = [
    //         'gender', 'blood', 'religion', 'monthlyincome', 'profession',
    //         'nationality', 'married', 'division', 'district', 'upazila'
    //     ];
        
    //     $emptyMemberFields = collect($requiredFields)->filter(function ($field) use ($member) {
    //         return empty($member->$field);
    //     });
        
    //     $member->update([
    //         'gender'         => $request->gender,
    //         'blood'          => $request->blood,
    //         'religion'       => $request->religion,
    //         'monthlyincome'  => $request->monthlyincome,
    //         'profession'     => $request->profession,
    //         'nationality'    => $request->nationality,
    //         'married'        => $request->married,
    //         'division'       => $request->division,
    //         'district'       => $request->district,
    //         'upazila'        => $request->upazila,
    //     ]);
        
        
        
       
        
    //     $verifyData = MemberVerify::where('member_id', $member->id)->latest()->first();
        
    //     $hasDocument = false;
    //     if ($verifyData) {
    //         if ($verifyData->type === 'nid') {
    //             $hasDocument = !(
    //                 empty($verifyData->nid_number) ||
    //                 empty($verifyData->nid_front_image) ||
    //                 empty($verifyData->nid_back_image)
    //             );
    //         } elseif ($verifyData->type === 'birth') {
    //             $hasDocument = !(
    //                 empty($verifyData->birth_number) ||
    //                 empty($verifyData->birth_image)
    //             );
    //         } elseif ($verifyData->type === 'passport') {
    //             $hasDocument = !empty($verifyData->passport_image);
    //         } elseif ($verifyData->type === 'driving') {
    //             $hasDocument = !(
    //                 empty($verifyData->driving_front_image) ||
    //                 empty($verifyData->driving_back_image)
    //             );
    //         }
    //     }
       
       
       
       
    //     $fieldsToCheck = [
    //         'gender', 'blood', 'religion', 'monthlyincome', 'profession',
    //         'nationality', 'married', 'division', 'district', 'upazila'
    //     ];
        
    //     $allFilled = true;
    //     foreach ($fieldsToCheck as $field) {
    //         if (empty($member->$field)) {
    //             $allFilled = false;
    //             break;
    //         }
    //     }
        
    //     $member->verified = $allFilled ? 1 : 0;
    //     $member->save();
    
    //     return response()->json([
    //         'message' => 'Member verification data successfully submitted!',
    //         'data' => $data,
    //     ], 200);
    // }


    // public function monetizationReport()
    // {
    //     $member = Auth::guard('member')->user();

    //     if (!$member) {
    //         return response()->json(['status' => 'failed', 'message' => 'Unauthorized'], 401);
    //     }

    //     $total_followers = Follow::where('following_id', $member->id)->count();
    //     $total_partners = Member::where('referrer_id', $member->id)->count();
    //     $total_reffers = Member::where('only_reffer', $member->id)->count();

    //     $partner_goal = 10;
    //     $reffer_goal = 100;
    //     $follower_goal = 1000;

    //     $partner_percentage = min(($total_partners / $partner_goal) * 100, 100);
    //     $reffer_percentage = min(($total_reffers / $reffer_goal) * 100, 100);
    //     $follower_percentage = min(($total_followers / $follower_goal) * 100, 100);

    //     $partner_status  = ($total_partners >= $partner_goal) ? 'Complete' : 'Incomplete';
    //     $reffer_status   = ($total_reffers >= $reffer_goal) ? 'Complete' : 'Incomplete';
    //     $follower_status = ($total_followers >= $follower_goal) ? 'Complete' : 'Incomplete';

    //     return response()->json([
    //         'status' => 'success',
    //         'data' => [
    //             'member_info' => [
    //                 'name' => $member->name,
    //                 'username' => $member->username,
    //             ],
    //             'stats' => [
    //                 'total_followers' => $total_followers,
    //                 'total_partners' => $total_partners,
    //                 'total_reffers' => $total_reffers,
    //             ],
    //             'requirements' => [
    //                 'partner' => [
    //                     'status' => $partner_status,
    //                     'current' => $total_partners,
    //                     'goal' => $partner_goal,
    //                     'percentage' => round($partner_percentage, 2) . '%'
    //                 ],
    //                 'follower' => [
    //                     'status' => $follower_status,
    //                     'current' => $total_followers,
    //                     'goal' => $follower_goal,
    //                     'percentage' => round($follower_percentage, 2) . '%'
    //                 ],
    //                 'reffer' => [
    //                     'status' => $reffer_status,
    //                     'current' => $total_reffers,
    //                     'goal' => $reffer_goal,
    //                     'percentage' => round($reffer_percentage, 2) . '%'
    //                 ],
    //             ]
    //         ]
    //     ], 200);
    // }

    public function monetizationReport()
    {
        try {
            $member = Auth::guard('member')->user();
            if (!$member) {
                return response()->json(['status' => 'failed', 'message' => 'Unauthorized'], 401);
            }

            // ১. বেসিক স্ট্যাটাস কাউন্ট
            $total_followers = Follow::where('following_id', $member->id)->count();
            $total_partners = Member::where('referrer_id', $member->id)->count();
            $total_reffers = Member::where('only_reffer', $member->id)->count();

            // ২. ভিডিও ওয়াচ টাইম ক্যালকুলেশন (সেকেন্ড থেকে ঘণ্টায় রূপান্তর)
            // আপনার video_views টেবিল থেকে মেম্বারের সব ভিডিওর মোট ওয়াচ টাইম
            $total_watch_seconds = DB::table('video_views')->where('member_id', $member->id)->sum('watch_time');
            $total_watch_hours = round($total_watch_seconds / 3600, 2);

            // ৩. গোল সেটিংস
            $partner_goal = 10;
            $reffer_goal = 100;
            $follower_goal = 1000;
            $watch_hour_goal = 4000;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'is_monetized' => $member->monetization_status == 1,
                    'stats' => [
                        'total_followers' => $total_followers,
                        'total_partners' => $total_partners,
                        'total_reffers' => $total_reffers,
                        'total_watch_hours' => $total_watch_hours,
                    ],
                    'requirements' => [
                        'follower' => $this->calcGoal($total_followers, $follower_goal),
                        'partner' => $this->calcGoal($total_partners, $partner_goal),
                        'reffer' => $this->calcGoal($total_reffers, $reffer_goal),
                        'watch_time' => $this->calcGoal($total_watch_hours, $watch_hour_goal),
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error("Monetization Report Error for Member {$member->id}: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Internal Server Error'], 500);
        }
    }

// হেল্পার ফাংশন পার্সেন্টেজ বের করার জন্য
private function calcGoal($current, $goal) {
    return [
        'status' => ($current >= $goal) ? 'Complete' : 'Incomplete',
        'current' => $current,
        'goal' => $goal,
        'percentage' => round(min(($current / $goal) * 100, 100), 2) . '%'
    ];
}

public function incomeHistory()
{
    try {
        // ১. বর্তমান মেম্বারকে গেট করা
        $member = Auth::guard('member')->user();

        if (!$member) {
            return response()->json(['status' => 'failed', 'message' => 'Unauthorized'], 401);
        }

        // ২. আর্নিংস টেবিল থেকে প্রতিদিনের ইনকাম রিপোর্ট আনা (লেটেস্ট আগে)
        // ৫০ মিলিয়ন ইউজারের কথা মাথায় রেখে paginate ব্যবহার করা হয়েছে
        $daily_history = DB::table('earnings')
            ->where('member_id', $member->id)
            ->select('amount', 'new_views', 'new_watch_time', 'earning_date')
            ->orderBy('earning_date', 'desc')
            ->paginate(15);

        // ৩. রেসপন্স ডাটা সাজানো
        return response()->json([
            'status' => 'success',
            'data' => [
                'member_summary' => [
                    'name' => $member->name,
                    'username' => $member->username,
                    'monetization_status' => $member->monetization == 1 ? 'Active' : 'Inactive',
                    'monetization_date' => $member->monetization_activated_at,
                ],
                'wallet' => [
                    'current_balance' => number_format($member->balance, 2), // তোলা যাবে এমন টাকা
                    'lifetime_earned' => number_format($member->total_earned, 2), // আজীবন মোট আয়
                ],
                'history' => $daily_history
            ]
        ], 200);

    } catch (\Exception $e) {
        // এরর হলে লগ ফাইলে জমা হবে
        Log::error("Income History Retrieval Failed for Member ID {$member->id}: " . $e->getMessage());
        
        return response()->json([
            'status' => 'error', 
            'message' => 'Internal server error. Please try again later.'
        ], 500);
    }
}



    public function update(Request $request)
    {
        try {
            $member = Auth::guard('member')->user();

            if (!$member) {
                return response()->json([
                    'status'  => 'failed',
                    'message' => 'Member not found'
                ], 404);
            }

            // --- ১. ভ্যালিডেশন (২০ এমবি পর্যন্ত অনুমতি দেওয়া হয়েছে) ---
            $validated = $request->validate([
                'name'          => 'sometimes|string|max:255',
                'email'         => 'nullable|email|max:255|unique:members,email,' . $member->id,
                'phone'         => 'sometimes|string|max:20',
                'address'       => 'nullable|string|max:500',
                'bio'           => 'nullable|string|max:1000',
                'location'      => 'nullable|string|max:255',
                'gender'        => 'nullable|string|max:50',
                'blood'         => 'nullable|string|max:5',
                'religion'      => 'nullable',
                'monthlyincome' => 'nullable|numeric',
                'nationality'   => 'nullable|string|max:100',
                'married'       => 'nullable',
                'division'      => 'nullable|string|max:100',
                'district'      => 'nullable|string|max:100',
                'upazila'       => 'nullable|string|max:100',
                'image'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:20480', 
            ]);

            // ব্যাকআপের জন্য বর্তমান ডাটা সংরক্ষণ
            $backupData = $member->only([
                'name', 'email', 'phone', 'image', 'address', 'bio', 'location', 'gender',
                'blood', 'religion', 'monthlyincome', 'nationality', 'married',
                'division', 'district', 'upazila'
            ]);

            $updatedFields = [];

            // টেক্সট ফিল্ডগুলো আপডেট করা
            foreach ($validated as $key => $value) {
                if ($key !== "image" && $member->$key != $value) {
                    $member->$key = $value;
                    $updatedFields[] = $key;
                }
            }

            // ================= ২. প্রোফাইল ইমেজ প্রসেসিং ও বাকেট ম্যানেজমেন্ট =================
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                
                // ফাইলের নাম তৈরি
                $originalName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
                $cleanName = time() . '-' . \Str::slug($originalName) . '.webp';
                $fileName = 'members/' . $cleanName;

                // ইমেজ রিসাইজ (৬০০x৬০০) ও অপ্টিমাইজেশন
                $img = \Image::make($image->getRealPath())->orientate();
                $img->fit(600, 600, function ($constraint) {
                    $constraint->upsize();
                });

                // WebP ফরম্যাটে এনকোড (প্রাথমিক কোয়ালিটি ৭৫%)
                $encodedImage = $img->encode('webp', 75);

                // যদি সাইজ ৫০০kb এর বেশি হয়, কোয়ালিটি আরও কমানো
                if (strlen($encodedImage->getEncoded()) > 500 * 1024) {
                    $encodedImage = $img->encode('webp', 60);
                }

                // GCS কানেকশন সেটআপ
                $keyFileData = config('filesystems.disks.gcs.key_file');
                if (!is_array($keyFileData)) {
                    $keyFileData = json_decode(file_get_contents(base_path($keyFileData)), true);
                }
                
                $storage = new \Google\Cloud\Storage\StorageClient([
                    'projectId' => config('filesystems.disks.gcs.project_id'),
                    'keyFile' => $keyFileData,
                ]);

                $bucketName = config('filesystems.disks.gcs.bucket');
                $bucket = $storage->bucket($bucketName);

                // --- পুরনো ইমেজ বাকেট থেকে ডিলিট করা ---
                if ($member->image) {
                    try {
                        // ১. পুরো URL থেকে শুধু পাথটি আলাদা করা
                        $oldPath = parse_url($member->image, PHP_URL_PATH); // যেমন: /bucket-name/members/filename.webp
                        
                        // ২. বাকেট নেমটি পাথ থেকে সরিয়ে ফেলা
                        // আমরা সরাসরি basename বা Str::after ব্যবহার করতে পারি আরও নিখুঁত হতে
                        $searchString = '/' . $bucketName . '/';
                        $relativeOldPath = "";

                        if (strpos($oldPath, $searchString) !== false) {
                            $relativeOldPath = explode($searchString, $oldPath)[1];
                        } else {
                            // যদি বাকেট নেম পাথে না থাকে, তবে স্লাশ ট্রিম করে ট্রাই করা
                            $relativeOldPath = ltrim($oldPath, '/');
                        }

                        // ৩. অবজেক্টটি ধরে ডিলিট করা
                        if (!empty($relativeOldPath)) {
                            $oldObject = $bucket->object($relativeOldPath);
                            if ($oldObject->exists()) {
                                $oldObject->delete();
                            }
                        }
                    } catch (\Exception $deleteError) {
                        \Log::warning("Old GCS image delete failed: " . $deleteError->getMessage());
                    }
                }

                // --- নতুন ইমেজ আপলোড করা ---
                $object = $bucket->upload($encodedImage->getEncoded(), [
                    'name' => $fileName,
                    'metadata' => [
                        'contentType' => 'image/webp'
                    ]
                ]);

                if ($object) {
                    $member->image = "https://storage.googleapis.com/" . $bucketName . "/" . $fileName;
                    $updatedFields[] = 'image';
                }
            }

            // --- ৩. ব্যাকআপ সেভ এবং ডাটাবেস আপডেট ---
            if (!empty($updatedFields)) {
                \App\Models\Memberbackup::create([
                    'member_id'     => $member->id,
                    'backup_data'   => json_encode($backupData),
                    'updated_fields'=> json_encode($updatedFields),
                    'created_at'    => now(),
                ]);

                $member->save();
            }

            return response()->json([
                'status'         => 'success',
                'message'        => 'Profile updated successfully!',
                'updated_fields' => $updatedFields,
                'data'           => $member,
            ]);

        } catch (\Exception $e) {
            \Log::error("Member Update Final Error: " . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }
    
    
    
    // public function update(Request $request)
    // {
    //     try {
    //         // --- Validate input
    //         $validated = $request->validate([
    //             'name'         => 'sometimes|string|max:255',
    //             'email'        => 'sometimes|email|max:255|unique:members,email,' . Auth::guard('member')->id(),
    //             'phone'        => 'sometimes|string|max:20',
    //             'address'      => 'nullable|string|max:500',
    //             'bio'          => 'nullable|string|max:1000',
    //             'location'     => 'nullable|string|max:255',
    //             'gender'       => 'sometimes|string|max:50',
    //             'blood'        => 'sometimes|string|max:5',
    //             'religion'     => 'sometimes',
    //             'monthlyincome'=> 'sometimes|numeric',
    //             'profession'   => 'sometimes|string|max:255',
    //             'nationality'  => 'sometimes|string|max:100',
    //             'married'      => 'sometimes',
    //             'division'     => 'sometimes|string|max:100',
    //             'district'     => 'sometimes|string|max:100',
    //             'upazila'      => 'sometimes|string|max:100',
    //             'image'        => 'sometimes|image|mimes:jpg,jpeg,png,webp|max:2048',
    //         ]);
    //         // return $request->all();
    //         $member = Auth::guard('member')->user();
    
    //         if (!$member) {
    //             return response()->json([
    //                 'status'  => 'failed',
    //                 'message' => 'Member not found'
    //             ], 404);
    //         }
    //         // --- Backup old data
    //         $backupData = $member->only([
    //             'name', 'email', 'phone', 'image', 'address', 'bio', 'location', 'gender',
    //             'blood', 'religion', 'monthlyincome', 'profession', 'nationality', 'married',
    //             'division', 'district', 'upazila'
    //         ]);
    
    //         $updatedFields = [];
            
    //         // Update image
    //         foreach ($validated as $key => $value) {
    //             if ($key !== "image" && $member->$key != $value) {
    //                 $member->$key = $value;
    //                 $updatedFields[] = $key;
    //             }
    //         }
    
    //        // ================= Profile Image =================
            
    //         $image = $request->file("image");
    //         if ($image) {
    //             $name = time() . "-" . $image->getClientOriginalName();
    //             $name = preg_replace('"\.(jpg|jpeg|png|webp)$"', ".webp", $name);
    //             $name = strtolower(Str::slug($name));
    //             $uploadpath = "public/uploads/members/";
    //             $imageUrl = $uploadpath . $name;
    //             $img = Image::make($image->getRealPath());
    //             $img->encode("webp", 90);
    //             $width = 120;
    //             $height = 120;
    //             $img->resize($width, $height);
    //             $img->save($imageUrl);
    //             $imageUrl = $imageUrl;
                
    //             $member->image = $imageUrl;
    //             $updatedFields[] = 'image';
    //         } else {
    //             $imageUrl = $member->image;
    // }
                
                
                
    //         // --- Save backup & member data updated
    //         if (!empty($updatedFields)) {
    //             Memberbackup::create([
    //                 'member_id'     => $member->id,
    //                 'backup_data'   => json_encode($backupData),
    //                 'updated_fields'=> json_encode($updatedFields),
    //                 'created_at'    => now(),
    //             ]);
    
    //             $member->save();
    //         }
    
    //         return response()->json([
    //             'status'         => 'success',
    //             'message'        => 'Member updated successfully!',
    //             'updated_fields' => $updatedFields,
    //             'data'           => $member,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status'  => 'error',
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    
   
    
    // public function update(Request $request)
    // {
       
    //     $memberId = Auth::guard('member')->user()->id;
    //     $member = Member::find($memberId);
    
    //     if (!$member) {
    //         return response()->json(['status' => 'failed', 'message' => 'Member not found'], 404);
    //     }
        
        
        
        
    //     // --- পুরানো ডেটা ব্যাকআপ রাখার জন্য
    //     $backupData = $member->only([
    //         'name', 'email', 'phone', 'image', 'address', 'bio', 'location', 'gender',
    //         'blood', 'religion', 'monthlyincome', 'profession', 'nationality', 'married',
    //         'division', 'district', 'upazila'
    //     ]);
    
    //     $updatedFields = [];
    
    //     // --- ফিল্ড আপডেট চেক
    //     $fieldsToCheck = [
    //         'name', 'email', 'phone', 'address', 'bio', 'location', 'gender',
    //         'blood', 'religion', 'monthlyincome', 'profession', 'nationality',
    //         'married', 'division', 'district', 'upazila'
    //     ];
    
    //     foreach ($fieldsToCheck as $field) {
    //         if ($request->filled($field) && $request->$field != $member->$field) {
    //             $member->$field = $request->$field;
    //             $updatedFields[] = $field;
    //         }
    //     }
    
    //   // ================= Profile Image =================
    //     $image = $request->file('image');
    //     if ($image) {
        
    //         // ✅ Unique file name
    //         $name = time() . '-profile-' . $image->getClientOriginalName();
    //         $name = preg_replace('"\.(jpg|jpeg|png|webp)$"', '.webp', $name);
    //         $name = strtolower(preg_replace('/\s+/', '-', $name));
        
    //         // ✅ Correct upload path (no double public/)
    //         $uploadPath = 'public/uploads/members/';
    //         $imageUrl = $uploadPath . $name;
        
    //         // ✅ Ensure folder exists
    //         if (!file_exists(public_path($uploadPath))) {
    //             mkdir(public_path($uploadPath), 0775, true);
    //         }
        
    //         // ✅ Image optimize & resize
    //         $targetWidth = 600;
    //         $img = Image::make($image->getRealPath());
    //         $img->resize($targetWidth, null, function ($constraint) {
    //             $constraint->aspectRatio();
    //             $constraint->upsize();
    //         });
        
    //         $quality = 90;
    //         do {
    //             $temp = 'temp_' . $name;
    //             $tempPath = public_path($uploadPath . $temp);
        
    //             $img->encode('webp', $quality)->save($tempPath);
    //             $size = filesize($tempPath) / 1024 / 1024;
    //             $quality -= 5;
        
    //         } while ($size > 2 && $quality >= 10);
        
    //         // ✅ Delete old image if exists
    //         if ($member->image && file_exists(public_path($member->image))) {
    //             unlink(public_path($member->image));
    //         }
        
    //         // ✅ Move optimized final image
    //         rename($tempPath, public_path($imageUrl));
        
    //         $member->image = $imageUrl;
    //         $updatedFields[] = 'image';
    //     }
    
    //     // --- ব্যাকআপ ও আপডেট
    //     if (!empty($updatedFields)) {
    //         // Backup to memberbackups table
    //         \App\Models\Memberbackup::create([
    //             'member_id' => $member->id,
    //             'backup_data' => json_encode($backupData),
    //             'updated_fields' => json_encode($updatedFields),
    //             'created_at' => now(),
    //         ]);
    
    //         $member->save();
    //     }
    
    //     return response()->json([
    //         'status'         => 'success',
    //         'message'        => 'Member updated successfully!',
    //         'updated_fields' => $updatedFields,
    //         'data'           => $member,
    //     ]);
    // }
    
    public function notification(Request $request)
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'firebase_id'  => 'nullable|string',
            'status'       => 'nullable|in:0,1', 
        ]);
    
        try {
    
            $notification = Notification::create([
                'member_id'   => Auth::guard('member')->id(),
                'title'       => $validated['title'],
                'description' => $validated['description'] ?? null,
                'firebase_id' => $validated['firebase_id'] ?? null,
                'status'      => $validated['status'] ?? 1,
            ]);
    
            return response()->json([
                'success' => true,
                'message' => 'Notification created successfully',
                'data'    => $notification
            ]);
    
        } catch (\Exception $e) {
    
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    
    
    
    public function notification_list(Request $request)
    {
        try {
    
            $memberId = Auth::guard('member')->id();
    
            $notifications = Notification::where('member_id', $memberId)
                ->orderBy('id', 'desc')
                ->paginate(20); 
    
            return response()->json([
                'success' => true,
                'message' => 'Notification list',
                'data'    => $notifications
            ]);
    
        } catch (\Exception $e) {
    
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    
    public function device_token(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required',
            'firebase_id' => 'required',
        ]);
        
    
        try {
    
            $member = Auth::guard('member')->user();
    
            $memberId   = $member->id;
            $memberName = $member->name;
            $deviceIp   = $request->device_ip;
    
        
            $device = DeviceToken::where('member_id', $memberId)->first();
    
            if ($device && $device->device_ip == $deviceIp) {

                $device->update([
                    'token' => $validated['token'],
                ]);
    
                $message = "Device token updated successfully";
        
    
            } else {
                $device = DeviceToken::create([
                    'member_id'   => $memberId,
                    'member_name' => $memberName,
                    'device_ip'   => $deviceIp,
                    'token'       => $validated['token'],
                    'firebase_id' => $request->firebase_id,
                    'status'      => 1,
                ]);
    
                $message = "Device token created successfully";
            }
    
            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $device
            ]);
    
        } catch (\Exception $e) {
    
            return response()->json([
                'success' => 'failed',
                'message' => 'Something went wrong',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    
    public function pagelist(Request $request)
    {
        try {
    
            $pages = CreatePage::orderBy('id', 'desc')->paginate(5);
    
            return response()->json([
                'success' => 'success',
                'message' => 'Page Loaded list ',
                'data'    => $pages
            ]);
    
        } catch (\Exception $e) {
    
            return response()->json([
                'success' => 'failed',
                'message' => 'Something went wrong',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }



    public function destroy($id)
    {
        $member = Member::find($id);

        if (!$member) {
            return response()->json(['status' => false, 'message' => 'Member not found'], 404);
        }

        $member->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Member deleted successfully!'
        ]);
    }
    
    
    
    // public function sendNotification(Request $request, \App\Services\FcmService $fcm)
    // {
    //     // Validate incoming request
    //     $data = $request->validate([
    //         'title'   => 'required|string',
    //         'body'    => 'required|string',
    //         'payload' => 'nullable|array',
    //     ]);
    
    //     // Fetch all device tokens from DB
    //     $tokens = DeviceToken::query()
    //         ->pluck('token')
    //         ->filter()
    //         ->unique()
    //         ->values()
    //         ->all();
    //     return $tokens;
    //     if (empty($tokens)) {
    //         return response()->json([
    //             'ok' => false,
    //             'error' => 'No device tokens found',
    //         ], 404);
    //     }
    
    //     $payload = array_merge($data['payload'] ?? [], [
    //         'type' => 'general_notification',
    //     ]);
    
    //     // Send FCM push notification
    //     $res = $fcm->sendToTokensSequential(
    //         $tokens,
    //         $data['title'],
    //         $data['body'],
    //         $payload
    //     );
    
    //     return response()->json([
    //         'ok' => true,
    //         'target' => 'tokens',
    //         'success_count' => $res['success'],
    //         'failure_count' => $res['failure'],
    //         'failed_tokens' => $res['failed_tokens'],
    //     ], 201);
    // }

    // public function sendNotification(Request $request)
    // {

    //     $receiverToken = $request->receiverToken;
    //     $title = $request->title;
    //     $body = $request->body;
    //     $data = $request->data ?? [];

    //     $accessToken = $this->getAccessToken();

    //     $projectId = "nabaax-1fdde";

    //     $response = Http::withToken($accessToken)
    //         ->post("https://fcm.googleapis.com/v1/projects/$projectId/messages:send", [

    //             "message" => [

    //                 "token" => $receiverToken,

    //                 "notification" => [
    //                     "title" => $title,
    //                     "body" => $body
    //                 ],

    //                 "data" => $data

    //             ]

    //         ]);

    //     return $response->json();

    // }

    // public function sendNotification(Request $request)
    // {
    //     $receiverToken = $request->receiverToken;
    //     $title = $request->title;
    //     $body = $request->body;
        
    //     // ১. ডাটা যদি খালি থাকে তবে এটি অবজেক্ট হিসেবে পাঠাতে হবে
    //     // ২. ডাটার ভেতরে সব ভ্যালু স্ট্রিং (String) হতে হবে
    //     $data = $request->data ?? [];
        
    //     // নিশ্চিত করুন ডাটা একটি Associative Array এবং সব ভ্যালু স্ট্রিং
    //     $formattedData = [];
    //     foreach ($data as $key => $value) {
    //         $formattedData[(string)$key] = (string)$value;
    //     }

    //     $accessToken = $this->getAccessToken();
    //     $projectId = "nabaax-1fdde";

    //     $response = Http::withToken($accessToken)
    //         ->post("https://fcm.googleapis.com/v1/projects/$projectId/messages:send", [
    //             "message" => [
    //                 "token" => $receiverToken,
    //                 "notification" => [
    //                     "title" => $title,
    //                     "body" => $body
    //                 ],
    //                 // যদি ডাটা খালি থাকে তবে ডাটা ফিল্ড পাঠানোর দরকার নেই বা খালি অবজেক্ট দিন
    //                 "data" => empty($formattedData) ? null : $formattedData
    //             ]
    //         ]);

    //     return $response->json();
    // }

    // public function sendNotification(Request $request)
    // {
    //     $memberId = $request->member_id;
    //     $title = $request->title;
    //     $body = $request->body;
    //     $data = $request->data ?? [];

    //     $formattedData = [];
    //     foreach ($data as $key => $value) {
    //         $formattedData[(string)$key] = (string)$value;
    //     }

    //     // ইউনিক টোকেনগুলো সংগ্রহ করা (ডুপ্লিকেট এড়াতে unique() যোগ করা হয়েছে)
    //     $deviceTokens = DB::table('device_tokens')
    //                     ->where('member_id', $memberId)
    //                     ->where('status', 1)
    //                     ->pluck('token')
    //                     ->unique();

    //     if ($deviceTokens->isEmpty()) {
    //         return response()->json(['message' => 'No active tokens found.'], 404);
    //     }

    //     $accessToken = $this->getAccessToken();
    //     $projectId = "nabaax-1fdde";
    //     $responses = [];

    //     foreach ($deviceTokens as $token) {
    //         $response = Http::withToken($accessToken)
    //             ->post("https://fcm.googleapis.com/v1/projects/$projectId/messages:send", [
    //                 "message" => [
    //                     "token" => $token,
    //                     "notification" => [
    //                         "title" => $title,
    //                         "body" => $body
    //                     ],
    //                     "data" => empty($formattedData) ? null : $formattedData
    //                 ]
    //             ]);

    //         $responseData = $response->json();

            
    //         if ($response->failed() && isset($responseData['error']['details'][0]['errorCode'])) {
    //             if ($responseData['error']['details'][0]['errorCode'] == 'UNREGISTERED') {
    //                 DB::table('device_tokens')->where('token', $token)->update(['status' => 0]);
    //             }
    //         }

    //         $responses[] = [
    //             'token' => substr($token, 0, 15) . '...',
    //             'status' => $response->successful() ? 'Success' : 'Failed', 
    //             'error_code' => $response->failed() ? ($responseData['error']['status'] ?? 'ERROR') : null
    //         ];
    //     }

    //     return response()->json([
    //         'total_unique_tokens' => count($deviceTokens),
    //         'results' => $responses
    //     ]);
    // }

    // public function sendNotification(Request $request)
    // {
    //     $memberId = $request->member_id;
    //     $title = $request->title;
    //     $body = $request->body;
    //     $data = $request->data ?? [];

    //     $formattedData = [];
    //     foreach ($data as $key => $value) {
    //         $formattedData[(string)$key] = (string)$value;
    //     }

    //     // ইউনিক টোকেনগুলো সংগ্রহ করা
    //     $deviceTokens = DB::table('device_tokens')
    //                     ->where('member_id', $memberId)
    //                     ->where('status', 1)
    //                     ->pluck('token')
    //                     ->unique();

    //     if ($deviceTokens->isEmpty()) {
    //         return response()->json(['message' => 'No active tokens found.'], 404);
    //     }

    //     $accessToken = $this->getAccessToken();
    //     $projectId = "nabaax-1fdde";
    //     $responses = [];
    //     $isSent = false; // ট্র্যাক করার জন্য যে নোটিফিকেশন সফল হয়েছে কি না

    //     foreach ($deviceTokens as $token) {
    //         $response = Http::withToken($accessToken)
    //             ->post("https://fcm.googleapis.com/v1/projects/$projectId/messages:send", [
    //                 "message" => [
    //                     "token" => $token,
    //                     "notification" => [
    //                         "title" => $title,
    //                         "body" => $body
    //                     ],
    //                     "data" => empty($formattedData) ? null : $formattedData
    //                 ]
    //             ]);

    //         $responseData = $response->json();

    //         if ($response->successful()) {
    //             $isSent = true; // অন্তত একটি সফল হলে ট্রু হবে
    //         }

    //         if ($response->failed() && isset($responseData['error']['details'][0]['errorCode'])) {
    //             if ($responseData['error']['details'][0]['errorCode'] == 'UNREGISTERED') {
    //                 DB::table('device_tokens')->where('token', $token)->update(['status' => 0]);
    //             }
    //         }

    //         $responses[] = [
    //             'token' => substr($token, 0, 15) . '...',
    //             'status' => $response->successful() ? 'Success' : 'Failed', 
    //             'error_code' => $response->failed() ? ($responseData['error']['status'] ?? 'ERROR') : null
    //         ];
    //     }

    //     // --- ডাটাবেসে নোটিফিকেশন সেভ করা ---
    //     // যদি আপনি চান নোটিফিকেশন পাঠানোর চেষ্টা করা হয়েছে এমন রেকর্ড রাখতে, তবে এই কন্ডিশন ছাড়াও সেভ করতে পারেন
    //     if ($isSent) {
    //         DB::table('notifications')->insert([
    //             'member_id'   => $memberId,
    //             'firebase_id' => null, // প্রোজেক্ট আইডি বা মেসেজ আইডি চাইলে এখানে দিতে পারেন
    //             'title'       => $title,
    //             'description' => $body,
    //             'status'      => 0, // ডিফল্ট আনরিড
    //             'created_at'  => now(),
    //             'updated_at'  => now(),
    //         ]);
    //     }

    //     return response()->json([
    //         'total_unique_tokens' => count($deviceTokens),
    //         'results' => $responses
    //     ]);
    // }


    public function sendNotification(Request $request)
    {
        $memberId = $request->member_id;
        $title = $request->title;
        $body = $request->body;
        $uid = $request->uid ?? null;  
        $data = $request->data ?? [];
        $notificationType = $request->notification_type ?? 'notype';

        $formattedData = [];
        foreach ($data as $key => $value) {
            $formattedData[(string)$key] = (string)$value;
        }
        $formattedData['notification_type'] = (string)$notificationType;
        $formattedData['click_action'] = 'FLUTTER_NOTIFICATION_CLICK';
        $formattedData['uid'] = (string)$uid;

        // ইউনিক টোকেনগুলো সংগ্রহ করা
        $deviceTokens = DB::table('device_tokens')
                        ->where('member_id', $memberId)
                        ->where('status', 1)
                        ->pluck('token')
                        ->unique();

        if ($deviceTokens->isEmpty()) {
            return response()->json(['message' => 'No active tokens found.'], 404);
        }

        $accessToken = $this->getAccessToken();
        $projectId = "nabaax-1fdde";
        $responses = [];
        $isSent = false; // ট্র্যাক করার জন্য যে নোটিফিকেশন সফল হয়েছে কি না

        foreach ($deviceTokens as $token) {
            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/$projectId/messages:send", [
                    "message" => [
                        "token" => $token,
                        "notification" => [
                            "title" => $title,
                            "body" => $body
                        ],
                        "data" => empty($formattedData) ? null : $formattedData
                    ]
                ]);

            $responseData = $response->json();

            if ($response->successful()) {
                $isSent = true; // অন্তত একটি সফল হলে ট্রু হবে
            }

            if ($response->failed() && isset($responseData['error']['details'][0]['errorCode'])) {
                if ($responseData['error']['details'][0]['errorCode'] == 'UNREGISTERED') {
                    DB::table('device_tokens')->where('token', $token)->update(['status' => 0]);
                }
            }

            $responses[] = [
                'token' => substr($token, 0, 15) . '...',
                'status' => $response->successful() ? 'Success' : 'Failed', 
                'error_code' => $response->failed() ? ($responseData['error']['status'] ?? 'ERROR') : null,
                'sent_data' => $formattedData
            ];
        }

        // --- ডাটাবেসে নোটিফিকেশন সেভ করা ---
        // যদি আপনি চান নোটিফিকেশন পাঠানোর চেষ্টা করা হয়েছে এমন রেকর্ড রাখতে, তবে এই কন্ডিশন ছাড়াও সেভ করতে পারেন
        if ($isSent) {
            DB::table('notifications')->insert([
                'member_id'   => $memberId,
                'firebase_id' => null, // প্রোজেক্ট আইডি বা মেসেজ আইডি চাইলে এখানে দিতে পারেন
                'title'       => $title,
                'description' => $body,
                'notification_type' => $notificationType,
                'status'      => 0, // ডিফল্ট আনরিড
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        return response()->json([
            'total_unique_tokens' => count($deviceTokens),
            'results' => $responses
        ]);
    }



    private function getAccessToken()
    {

        $client = new Client();

        $client->setAuthConfig(storage_path('app/firebase.json'));

        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        $client->refreshTokenWithAssertion();

        $token = $client->getAccessToken();

        return $token['access_token'];

    }

    public function readNotification(Request $request)
    {
        $request->validate([
            'notification_id' => 'required|exists:notifications,id'
        ]);

        $memberId = Auth::guard('member')->user()->id; 

        $updated = DB::table('notifications')
                    ->where('id', $request->notification_id)
                    ->where('member_id', $memberId) 
                    ->update([
                        'status' => 1,
                        'updated_at' => now()
                    ]);

        if ($updated) {
            return response()->json([
                'status' => 'success',
                'message' => 'Notification marked as read.'
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Notification not found or unauthorized.'
        ], 404);
    }

    public function saveNotification(Request $request)
    {
        $request->validate([
            'member_id' => 'required',
            'title' => 'required',
            'body' => 'required',
            'type' => 'required',
            'notification_id' => 'nullable' 
        ]);

        $memberId = $request->member_id;
        $title = $request->title;
        $body = $request->body;
        $type = $request->type;
        $notificationId = $request->notification_id ?? null;

        try {
            DB::table('notifications')->insert([
                'member_id'       => $memberId,
                'notification_id' => $notificationId, 
                'firebase_id'     => null, 
                'title'           => $title,
                'description'     => $body,
                'type'            => $type,
                'status'          => 0, 
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Notification saved to database successfully.'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save notification: ' . $e->getMessage()
            ], 500);
        }
    }


    public function messageWithNotification(Request $request, \App\Services\FcmService $fcm)
    {
        return "ok";
        $data = $request->validate([
            'title'      => 'required|string',
            'body'       => 'required|string',
            'payload'    => 'nullable|array',
            'firebase_id'=> 'required|string',
        ]);
        
        // return $request;
    
        // find the device record by firebase_id
        $device = DeviceToken::where('firebase_id', $data['firebase_id'])
                    ->select('id','member_id','firebase_id','token')
                    ->first();
    
        if (!$device || empty($device->token)) {
            return response()->json([
                'ok' => false,
                'error' => 'Device token not found for this firebase_id',
            ], 404);
        }
    
        $payload = array_merge($data['payload'] ?? [], [
            'type' => 'general_notification',
        ]);
    
        try {
        
            $messageId = $fcm->sendToToken(
                $device->token,
                $data['title'],
                $data['body'],
                $payload
            );
    
            return response()->json([
                'ok' => true,
                'target' => 'token',
                'message_id' => $messageId,
            ], 200);
        } catch (\Throwable $e) {
            // log and return failure
            \Log::error('FCM send error: '.$e->getMessage(), ['device' => $device->toArray()]);
            return response()->json([
                'ok' => false,
                'error' => 'Failed to send notification',
                'message' => $e->getMessage(),
            ], 500);
        }
    }




}
