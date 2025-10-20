<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Customer\CustomerController;

Route::prefix('customer')->group(function () {
    Route::post('initiate-chat',   [CustomerController::class, 'initiateChat']);
    Route::post('verify-otp', [CustomerController::class, 'verifyOtp']);
    Route::post('resend-otp', [CustomerController::class, 'resendOtp']);
    Route::get('website/conversation/{token}', [CustomerController::class, 'getCustomerWebsiteConversation'])->middleware('customer.token');
});