<?php

use App\Http\Controllers\Api\User\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('user')->group(function () {
    Route::post('login', [UserController::class, 'login']);
    Route::post('verify-otp', [UserController::class, 'verifyOtp']);
    Route::post('resend-otp', [UserController::class, 'resendOtp']);
    Route::post('password/reset-request', [UserController::class, 'passwordResetRequest']);
    Route::post('password/reset', [UserController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/list', [UserController::class, 'getUserList']);
        Route::get('me', [UserController::class, 'getMe']);
        Route::post('create', [UserController::class, 'createUser'])->middleware('supervisor');
        Route::post('create', [UserController::class, 'createUser'])->middleware('supervisor');
        Route::post('logout', [UserController::class, 'logout']);
        Route::post('update-password', [UserController::class, 'updatePassword']);
        // Route::post('update-profile', [UserController::class, 'updateProfile']);
    });
});
