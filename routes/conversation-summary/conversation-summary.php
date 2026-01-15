<?php

use App\Http\Controllers\Api\ConversationSummary\ConversationTypeController;
use App\Http\Controllers\Api\ConversationSummary\CustomerModeController;
use App\Http\Controllers\Api\ConversationSummary\SubwrapUpConversationController;
use Illuminate\Support\Facades\Route;

Route::prefix('conversation/')->middleware(['auth:sanctum', 'role:Super Admin,Admin,Supervisor'])->group(function () { 
    Route::apiResource('types', ConversationTypeController::class);
    Route::apiResource('customer-modes', CustomerModeController::class);
    Route::apiResource('sub-wrap-ups', SubwrapUpConversationController::class);
});
