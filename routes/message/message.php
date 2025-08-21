<?php

use App\Http\Controllers\Api\Message\QuickReplyController;
use App\Http\Controllers\Api\Message\UserQuickReplyController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    Route::apiResource('user-quick-replies', UserQuickReplyController::class);
    Route::delete('user-quick-replies/{id}', [UserQuickReplyController::class, 'destroy']);

    Route::apiResource('quick-replies', QuickReplyController::class);
    Route::delete('quick-replies/{id}', [QuickReplyController::class, 'destroy']);
});

