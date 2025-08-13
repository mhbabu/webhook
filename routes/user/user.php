<?php

use App\Http\Controllers\Api\User\UserController;
use Illuminate\Support\Facades\Route;


Route::post('user/login', [UserController::class, 'login']);
Route::post('user/verify-otp', [UserController::class, 'verifyOtp']);
Route::post('user/resend-otp', [UserController::class, 'resendOtp']);
Route::post('user/password/reset-request', [UserController::class, 'passwordResetRequest']);
Route::post('user/reset-password', [UserController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('user/create', [UserController::class, 'createUser'])->middleware('supervisor');
    Route::get('me', [UserController::class, 'getMe']);
    Route::post('user/logout', [UserController::class, 'logout']);
    Route::post('user/update-password', [UserController::class, 'updatePassword']);
    Route::post('user/update-profile', [UserController::class, 'updateProfile']);
});
