<?php

use App\Http\Controllers\Api\ConversationSummary\ConversationTypeController;
use App\Http\Controllers\Api\ConversationSummary\CustomerModeController;
use App\Http\Controllers\Api\ConversationSummary\SubwrapUpConversationController;
use Illuminate\Support\Facades\Route;

Route::prefix('conversation')->group(function () {

    // 🔓 Public / authenticated read-only routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('types', [ConversationTypeController::class, 'index']);
        Route::get('types/{type}', [ConversationTypeController::class, 'show']);

        Route::get('customer-modes', [CustomerModeController::class, 'index']);
        Route::get('customer-modes/{mode}', [CustomerModeController::class, 'show']);

        Route::get('sub-wrap-ups', [SubwrapUpConversationController::class, 'index']);
        Route::get('sub-wrap-ups/{subWrapUp}', [SubwrapUpConversationController::class, 'show']);
    });

    // 🔒 Restricted write routes
    Route::middleware(['auth:sanctum', 'role:Super Admin,Admin,Supervisor'])->group(function () {

        Route::apiResource('types', ConversationTypeController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('customer-modes', CustomerModeController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('sub-wrap-ups', SubwrapUpConversationController::class)->only(['store', 'update', 'destroy']);
    });
});
