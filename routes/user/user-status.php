<?php

use App\Http\Controllers\Api\User\UserStatusUpdateController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('user')->group(function () {
    Route::post('status/update', [UserStatusUpdateController::class, 'updateUserStatus']);
    Route::post('status/{id}/approve', [UserStatusUpdateController::class, 'approve']);
    Route::get('statuses', [UserStatusUpdateController::class, 'getStatuses']);
});
