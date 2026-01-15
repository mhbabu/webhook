<?php

use App\Http\Controllers\Api\ConversationSummary\ConversationTypeController;
use App\Http\Controllers\Api\ConversationSummary\CustomerModeController;
use App\Http\Controllers\Api\ConversationSummary\WrapUpSubConversationController;
use Illuminate\Support\Facades\Route;

Route::prefix('conversations')->group(function () {

    // interaction types
    // Route::get('/types', [ConversationTypeController::class, 'index']);
    // Route::post('/types', [ConversationTypeController::class, 'store']);
    // Route::put('/types/{id}', [ConversationTypeController::class, 'update']);
    // Route::delete('/types/{id}', [ConversationTypeController::class, 'destroy']);

    Route::apiResource('types', ConversationTypeController::class);
    Route::apiResource('modes', CustomerModeController::class);
    Route::apiResource('wrap-up/sub', WrapUpSubConversationController::class);

    // Route::apiResource('customer-modes', CustomerModeController::class)
    //     ->except(['show']);

    // Route::apiResource('wrap-up/sub', WrapUpSubConversationController::class)
    //     ->except(['show']);

});
