<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Frontend\FrontendController;
use App\Http\Controllers\Admin\DistrictController;
use App\Http\Controllers\Admin\GeneralSettingController;
use App\Http\Controllers\Admin\PaymentChargeSettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Frontend\CustomerController;
use App\Http\Controllers\Admin\CreatePageController;
use App\Http\Controllers\Admin\DepositController;
use App\Http\Controllers\Admin\WithdrawController;


Auth::routes();

Route::get('/cc', function () {
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('migrate');
    return "Cleared!";
});


// Route::get('/controller', function () {
//     Artisan::call('make:controller Admin/DepositController');
//     return "Controller Created";
// });

Route::get('/migrate', function () {
    Artisan::call('migrate');
    return "Migrate Done!";
});

// Route::get('/model', function () {
//     Artisan::call('make:model Notification -m');
//     return "Model Done!";
// });






Route::group(['namespace' => 'Frontend', 'middleware' => ['ipcheck', 'check_refer']], function () {

    
    Route::get('/', [FrontendController::class, 'index'])->name('home');
    Route::get('/privary-policy', [FrontendController::class, 'privary_policy']);
    Route::get('/admob', [FrontendController::class, 'admob']);
    Route::get('/childsafety-standards', [FrontendController::class, 'childsafety_standards']);
    
    
});



Route::group(['prefix' => 'customer', 'namespace' => 'Frontend', 'middleware' => ['ipcheck', 'check_refer']], function () {
    Route::get('/login', [CustomerController::class, 'login'])->name('customer.login');
    Route::post('/signin', [CustomerController::class, 'signin'])->name('customer.signin');
    Route::get('/register', [CustomerController::class, 'register'])->name('customer.register');
    Route::post('/store', [CustomerController::class, 'store'])->name('customer.store');
    Route::get('/verify', [CustomerController::class, 'verify'])->name('customer.verify');
    Route::post('/verify-account', [CustomerController::class, 'account_verify'])->name('customer.account.verify');
    Route::post('/resend-otp', [CustomerController::class, 'resendotp'])->name('customer.resendotp');
    Route::post('/logout', [CustomerController::class, 'logout'])->name('customer.logout');
    Route::post('/post/review', [CustomerController::class, 'review'])->name('customer.review');
    Route::get('/forgot-password', [CustomerController::class, 'forgot_password'])->name('customer.forgot.password');
    Route::post('/forgot-verify', [CustomerController::class, 'forgot_verify'])->name('customer.forgot.verify');
    Route::get('/forgot-password/reset', [CustomerController::class, 'forgot_reset'])->name('customer.forgot.reset');
    Route::post('/forgot-password/store', [CustomerController::class, 'forgot_store'])->name('customer.forgot.store');
    Route::post('/forgot-password/resendotp', [CustomerController::class, 'forgot_resend'])->name('customer.forgot.resendotp');
    Route::get('/checkout', [CustomerController::class, 'checkout'])->name('customer.checkout');
    Route::post('/order-save', [CustomerController::class, 'order_save'])->name('customer.ordersave');
    Route::get('/order-success/{id}', [CustomerController::class, 'order_success'])->name('customer.order_success');
    Route::get('/order-track', [CustomerController::class, 'order_track'])->name('customer.order_track');
    Route::get('/order-track/result', [CustomerController::class, 'order_track_result'])->name('customer.order_track_result');
});
// customer auth
Route::group(['prefix' => 'customer', 'namespace' => 'Frontend', 'middleware' => ['customer', 'ipcheck', 'check_refer']], function () {

    Route::get('/account', [CustomerController::class, 'account'])->name('customer.account');

    Route::get('/login', [CustomerController::class, 'login'])->name('customer.login');
    Route::get('/invoice', [CustomerController::class, 'invoice'])->name('customer.invoice');
    Route::get('/pdf-reader', [CustomerController::class, 'pdfreader'])->name('customer.pdfreader');
    Route::get('/invoice/order-note', [CustomerController::class, 'order_note'])->name('customer.order_note');
    Route::get('/profile-edit', [CustomerController::class, 'profile_edit'])->name('customer.profile_edit');
    Route::post('/profile-update', [CustomerController::class, 'profile_update'])->name('customer.profile_update');
    Route::get('/change-password', [CustomerController::class, 'change_pass'])->name('customer.change_pass');
    Route::post('/password-update', [CustomerController::class, 'password_update'])->name('customer.password_update');
});




// unathenticate admin route
Route::group(['namespace' => 'Admin', 'prefix' => 'nb65vartex', 'middleware' => ['customer', 'ipcheck', 'check_refer']], function () {
    Route::get('locked', [DashboardController::class, 'locked'])->name('locked');
    Route::post('unlocked', [DashboardController::class, 'unlocked'])->name('unlocked');
}); 


// auth route
Route::group(['namespace' => 'Admin', 'middleware' => ['auth', 'lock', 'check_refer'], 'prefix' => 'nb65vartex'], function () {
    Route::get('dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');
    // Route::get('change-password', [DashboardController::class, 'changepassword'])->name('change_password');
    Route::post('new-password', [DashboardController::class, 'newpassword'])->name('new_password');

    // users route
    Route::get('users/manage', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users/save', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{id}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::post('users/update', [UserController::class, 'update'])->name('users.update');
    Route::post('users/inactive', [UserController::class, 'inactive'])->name('users.inactive');
    Route::post('users/active', [UserController::class, 'active'])->name('users.active');
    Route::post('users/destroy', [UserController::class, 'destroy'])->name('users.destroy');

    // roles
    Route::get('roles/manage', [RoleController::class, 'index'])->name('roles.index');
    Route::get('roles/{id}/show', [RoleController::class, 'show'])->name('roles.show');
    Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('roles/save', [RoleController::class, 'store'])->name('roles.store');
    Route::get('roles/{id}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::post('roles/update', [RoleController::class, 'update'])->name('roles.update');
    Route::post('roles/destroy', [RoleController::class, 'destroy'])->name('roles.destroy');

    // permissions
    Route::get('permissions/manage', [PermissionController::class, 'index'])->name('permissions.index');
    Route::get('permissions/{id}/show', [PermissionController::class, 'show'])->name('permissions.show');
    Route::get('permissions/create', [PermissionController::class, 'create'])->name('permissions.create');
    Route::post('permissions/save', [PermissionController::class, 'store'])->name('permissions.store');
    Route::get('permissions/{id}/edit', [PermissionController::class, 'edit'])->name('permissions.edit');
    Route::post('permissions/update', [PermissionController::class, 'update'])->name('permissions.update');
    Route::post('permissions/destroy', [PermissionController::class, 'destroy'])->name('permissions.destroy');


    // deposit code route
    Route::get('deposit/manage', [DepositController::class,'index'])->name('deposit.index');
    Route::get('deposit/history', [DepositController::class,'history'])->name('deposit.history');
    Route::post('deposit/status', [DepositController::class,'status'])->name('deposit.status');

    // withdraw code route
    Route::get('withdraw/manage', [WithdrawController::class,'index'])->name('withdraw.index');
    Route::get('withdraw/history', [WithdrawController::class,'history'])->name('withdraw.history');
    Route::post('withdraw/status', [WithdrawController::class,'status'])->name('withdraw.status');

    // Payment Charge Setting route
    Route::get('paymentcharges/manage', [PaymentChargeSettingController::class, 'index'])->name('paymentcharges.index');
    Route::get('paymentcharges/create', [PaymentChargeSettingController::class, 'create'])->name('paymentcharges.create');
    Route::post('paymentcharges/save', [PaymentChargeSettingController::class, 'store'])->name('paymentcharges.store');
    Route::get('paymentcharges/{id}/edit', [PaymentChargeSettingController::class, 'edit'])->name('paymentcharges.edit');
    Route::post('paymentcharges/update', [PaymentChargeSettingController::class, 'update'])->name('paymentcharges.update');
    Route::post('paymentcharges/inactive', [PaymentChargeSettingController::class, 'inactive'])->name('paymentcharges.inactive');
    Route::post('paymentcharges/active', [PaymentChargeSettingController::class, 'active'])->name('paymentcharges.active');
    Route::post('paymentcharges/destroy', [PaymentChargeSettingController::class, 'destroy'])->name('paymentcharges.destroy');


    // settings route
    Route::get('settings/manage', [GeneralSettingController::class, 'index'])->name('settings.index');
    Route::get('settings/create', [GeneralSettingController::class, 'create'])->name('settings.create');
    Route::post('settings/save', [GeneralSettingController::class, 'store'])->name('settings.store');
    Route::get('settings/{id}/edit', [GeneralSettingController::class, 'edit'])->name('settings.edit');
    Route::post('settings/update', [GeneralSettingController::class, 'update'])->name('settings.update');
    Route::post('settings/inactive', [GeneralSettingController::class, 'inactive'])->name('settings.inactive');
    Route::post('settings/active', [GeneralSettingController::class, 'active'])->name('settings.active');
    Route::post('settings/destroy', [GeneralSettingController::class, 'destroy'])->name('settings.destroy');
    
    
    
    
    // contact route
    Route::get('contact/manage', [ContactController::class, 'index'])->name('contact.index');
    Route::get('contact/create', [ContactController::class, 'create'])->name('contact.create');
    Route::post('contact/save', [ContactController::class, 'store'])->name('contact.store');
    Route::get('contact/{id}/edit', [ContactController::class, 'edit'])->name('contact.edit');
    Route::post('contact/update', [ContactController::class, 'update'])->name('contact.update');
    Route::post('contact/inactive', [ContactController::class, 'inactive'])->name('contact.inactive');
    Route::post('contact/active', [ContactController::class, 'active'])->name('contact.active');
    Route::post('contact/destroy', [ContactController::class, 'destroy'])->name('contact.destroy');


    // contact route
    Route::get('page/manage', [CreatePageController::class, 'index'])->name('pages.index');
    Route::get('page/create', [CreatePageController::class, 'create'])->name('pages.create');
    Route::post('page/save', [CreatePageController::class, 'store'])->name('pages.store');
    Route::get('page/{id}/edit', [CreatePageController::class, 'edit'])->name('pages.edit');
    Route::post('page/update', [CreatePageController::class, 'update'])->name('pages.update');
    Route::post('page/inactive', [CreatePageController::class, 'inactive'])->name('pages.inactive');
    Route::post('page/active', [CreatePageController::class, 'active'])->name('pages.active');
    Route::post('page/destroy', [CreatePageController::class, 'destroy'])->name('pages.destroy');

    // district routes
    Route::get('district/manage', [DistrictController::class, 'index'])->name('districts.index');
    Route::get('district/{id}/edit', [DistrictController::class, 'edit'])->name('districts.edit');
    Route::post('district/update', [DistrictController::class, 'update'])->name('districts.update');
    Route::post('district/charge-update', [DistrictController::class, 'district_charge'])->name('districts.charge');
});
