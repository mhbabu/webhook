<?php

use App\Http\Controllers\Api\User\UserCategoryController;
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

        // Routes only for Super Admin, Admin, Supervisor
        Route::middleware('role:Super Admin,Admin,Supervisor')->group(function () {
            Route::apiResource('categories', UserCategoryController::class)->except(['edit', 'create']);
            Route::delete('categories/{category}', [UserCategoryController::class, 'destroy']);
            Route::post('create', [UserController::class, 'createUser']);
            Route::get('list', [UserController::class, 'getUserList']);
            Route::get('former/list', [UserController::class, 'getFormerUserList']);
        });

        // Other routes for all authenticated users
        Route::get('me', [UserController::class, 'getMe']);
        Route::get('{user}', [UserController::class, 'getUserById']);
        Route::post('logout', [UserController::class, 'logout'])->name('user.logout');
        Route::post('update-password', [UserController::class, 'updatePassword']);
        Route::post('update-profile/{user}', [UserController::class, 'updateUserProfile']);
    });
});
