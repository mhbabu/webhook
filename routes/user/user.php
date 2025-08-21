<?php

use App\Http\Controllers\Api\User\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('user')->group(function () {
    Route::post('login', [UserController::class, 'login']);
    Route::post('verify-otp', [UserController::class, 'verifyOtp']);
    Route::post('resend-otp', [UserController::class, 'resendOtp']);
    Route::post('password/reset-request', [UserController::class, 'passwordResetRequest']);
    Route::post('password/reset', [UserController::class, 'resetPassword']);

    // All authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        // Other routes for all authenticated users
        Route::get('me', [UserController::class, 'getMe']);
        Route::post('logout', [UserController::class, 'logout'])->name('user.logout');
        Route::post('update-password', [UserController::class, 'updatePassword']);
        
    });
});

Route::middleware(['auth:sanctum', 'role:Super Admin,Admin,Supervisor'])->group(function () {
    Route::apiResource('users', UserController::class);
    Route::get('former/users', [UserController::class, 'getFormerUserList']);
});

require __DIR__ .'/role.php';