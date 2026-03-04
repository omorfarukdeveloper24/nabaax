<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FrontendController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\PostMediaController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\Api\ShareController;
use App\Http\Controllers\Api\PostStatController;
use App\Http\Controllers\Api\FriendshipController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\PaymentServiceController;


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
return $request->user();
});


Route::group(['namespace' => 'Api','prefix'=>'v1'], function() {
   Route::get('districts', [FrontendController::class, 'getDistricts']);
   Route::get('division', [FrontendController::class, 'getDivisions']);
   Route::get('upazila', [FrontendController::class, 'getUpazilas']);
   Route::get('professions', [FrontendController::class, 'getProfessions']);
   
   Route::post('send-notification', [MemberController::class, 'sendNotification']); 
   Route::post('read-notification', [MemberController::class, 'readNotification']);
   Route::post('save-notification', [MemberController::class, 'saveNotification']); 
   Route::post('message-notification', [MemberController::class, 'messageWithNotification']); 
   
});
    
    
Route::group(['namespace' => 'Api','prefix'=>'v1','middleware' =>'auth.jwt'], function(){
Route::get('customer/login-check', [CustomerController::class, 'logincheck']);
Route::post('customer/logout', [CustomerController::class, 'logout']);
Route::get('customer/profile', [CustomerController::class, 'profile']);
Route::post('customer/change-password', [CustomerController::class, 'change_password'])->name('change_password');
Route::post('customer/profile-update', [CustomerController::class, 'profile_update'])->name('profile_update');
Route::get('customer/orders', [CustomerController::class, 'orders']);

Route::get('customer/orders', [CustomerController::class, 'orders']);

//=============== show customer wallet ===================//


});

Route::get('testing', function () {
    return 'Successful Test';
});


Route::group(['namespace' => 'Api','prefix'=>'v1','middleware' => 'api'], function(){
        Route::get('/country', [MemberController::class, 'country']); 
});
    


Route::group(['namespace' => 'Api','prefix'=>'v1','middleware' => 'api'], function(){
    
    Route::post('/member-login', [MemberController::class, 'signin']);
    Route::post('/member-logout', [MemberController::class, 'logout']);
    Route::get('/membersearch', [MemberController::class, 'membersearch']);  

    
    Route::prefix('member')->group(function () {
        Route::get('/list', [MemberController::class, 'list']);  
        Route::post('/store', [MemberController::class, 'store']); 
        Route::post('/forgot-password', [MemberController::class, 'forgot_password']); 
        Route::post('/forgot-verify', [MemberController::class, 'forgot_verify']); 
        Route::post('/change-password', [MemberController::class, 'change_password']); 
        Route::post('/new-password', [MemberController::class, 'new_password']); 
        Route::post('/pertnar-program', [MemberController::class, 'pertnar_program']); 
        Route::post('/monetization', [MemberController::class, 'monetization']); 
        Route::post('/notification', [MemberController::class, 'notification']); 
        Route::post('/device-token', [MemberController::class, 'device_token']); 
        Route::post('/phone-verify', [MemberController::class, 'phone_verify']); 
        Route::get('/my-profile', [MemberController::class, 'my_profile']); 
        Route::get('/profile', [MemberController::class, 'profile']); 
        Route::get('/notification-list', [MemberController::class, 'notification_list']); 
        // Route::post('/send-notification', [MemberController::class, 'sendNotification']); 
        Route::get('/allteam', [MemberController::class, 'allteam']); 
        Route::get('/pagelist', [MemberController::class, 'pagelist']); 
        Route::get('/monetization-report', [MemberController::class, 'monetizationReport']); 
        Route::get('/income-history', [MemberController::class, 'incomeHistory']); 
        Route::get('/approved_acount/{id}', [MemberController::class, 'approved_acount']); 
        Route::post('/update', [MemberController::class, 'update']); 
        Route::post('/destroy/{id}', [MemberController::class, 'destroy']); 
        Route::post('/verifywith-document', [MemberController::class, 'verifywithDocument']); 
                                                                                                                                                                                                                                                                                                                                          
        
        Route::post('/dpstore', [PaymentServiceController::class, 'dpstore']);  
        Route::post('/withdraw-store', [PaymentServiceController::class, 'withdraw_store']);  
        Route::post('/balance-transfer', [PaymentServiceController::class, 'balance_transfer']);

        Route::get('/dposit-list', [PaymentServiceController::class, 'deposit_list']);  
        Route::get('/withdraw-list', [PaymentServiceController::class, 'withdraw_list']);  
        Route::get('/transfer-list', [PaymentServiceController::class, 'transfer_list']);

        Route::get('/all-payment', [PaymentServiceController::class, 'all_payment']);
        Route::get('/receive-payment', [PaymentServiceController::class, 'receive_payment']);
        
        Route::get('/login-checkt', [MemberController::class, 'loginCheck']);
        
    });
    
    
    
    Route::prefix('friend')->middleware('member')->group(function () {
        Route::post('/send', [FriendshipController::class, 'sendRequest']);
        Route::post('/accept', [FriendshipController::class, 'acceptRequest']);
        Route::post('/reject', [FriendshipController::class, 'rejectRequest']);
        Route::post('/unfriend', [FriendshipController::class, 'unfriend']);
        Route::get('/list', [FriendshipController::class, 'friendlist']);
        Route::get('/pendinglist', [FriendshipController::class, 'pendingRequests']);
    });
    
    
    Route::prefix('follow')->middleware('member')->group(function () {
        Route::post('/dofollow', [FollowController::class, 'follow']); 
        Route::post('/unfollow', [FollowController::class, 'unfollow']); 
        Route::get('/followers', [FollowController::class, 'followers']); 
        Route::get('/following', [FollowController::class, 'following']); 
        Route::post('/followboost', [FollowController::class, 'followBoost']); 
        Route::get('/flowfriend', [FollowController::class, 'flowfriend']); 
        Route::get('/suggestions', [FollowController::class, 'suggestions']);
    });


    Route::prefix('post')->group(function () {
        Route::get('/list', [PostController::class, 'list']); 
        Route::get('/details/{id}', [PostController::class, 'details']); 
        Route::get('/prosearch', [PostController::class, 'prosearch']); 
        Route::get('/postvideo', [PostController::class, 'postvideo']);   
        Route::get('/personalpostvideo', [PostController::class, 'personalpostvideo']);   
    });
        
    // Post Routes
    Route::prefix('post')->middleware('member')->group(function () { 
        Route::post('/store', [PostController::class, 'store']);  
        Route::post('/miniads', [PostController::class, 'miniads']);  
        Route::post('/update/{id}', [PostController::class, 'update']); 
        Route::post('/track-view', [PostController::class, 'trackView']);
        Route::post('/destroy/{id}', [PostController::class, 'destroy']); 
        Route::post('/boost/click/{id}', [PostController::class, 'linkClick']);
    });


    Route::prefix('post-media')->middleware('member')->group(function () {
        Route::get('/list',    [PostMediaController::class, 'list']);  
        Route::post('/store',   [PostMediaController::class, 'store']);   
        Route::get('/details/{id}', [PostMediaController::class, 'details']); 
        Route::post('/update/{id}', [PostMediaController::class, 'update']); 
        Route::post('/destroy/{id}', [PostMediaController::class, 'destroy']); 
    });

    Route::prefix('comment')->middleware('member')->group(function () {
        Route::get('/list', [CommentController::class, 'list']); 
        Route::get('/replies', [CommentController::class, 'getReplies']);     
        Route::post('/store', [CommentController::class, 'store']);   
        Route::get('/details/{id}', [CommentController::class, 'details']);   
        Route::post('/update/{id}', [CommentController::class, 'update']); 
        Route::post('/destroy/{id}', [CommentController::class, 'destroy']); 
    });

    Route::prefix('like')->middleware('member')->group(function () {
        Route::get('/list', [LikeController::class, 'list']);        
        Route::post('/store', [LikeController::class, 'store']);       
        Route::get('/details/{id}', [LikeController::class, 'details']); 
        Route::post('/update', [LikeController::class, 'update']); 
        Route::post('/destroy', [LikeController::class, 'destroy']);
    });

    Route::prefix('share')->middleware('member')->group(function () {
        Route::get('/list',   [ShareController::class, 'list']);   
        Route::post('/store',  [ShareController::class, 'store']);  
        Route::get('/details/{id}', [ShareController::class, 'details']);  
        Route::post('/update/{id}', [ShareController::class, 'update']); 
        Route::post('/destroy/{id}', [ShareController::class, 'destroy']); 
    });

    Route::prefix('post-stat')->middleware('member')->group(function () {
        Route::get('/list', [PostStatController::class, 'list']);         
        Route::post('/store', [PostStatController::class, 'store']);        
        Route::get('/details/{id}', [PostStatController::class, 'details']);     
        Route::post('/update/{id}', [PostStatController::class, 'update']);  
        Route::post('/destroy/{id}', [PostStatController::class, 'destroy']); 
    });






   

});








