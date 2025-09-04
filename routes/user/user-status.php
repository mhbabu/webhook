<?php

use App\Http\Controllers\Api\User\UserStatusUpdateController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('user/status')->group(function () {
    Route::post('update', [UserStatusUpdateController::class, 'updateUserStatus']);
    Route::post('{id}/approve', [UserStatusUpdateController::class, 'approve']);
});
