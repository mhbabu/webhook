<?php

use App\Http\Controllers\Api\ConversationSummary\ConversationTypeController;
use App\Http\Controllers\Api\ConversationSummary\CustomerModeController;
use Illuminate\Support\Facades\Route;

Route::prefix('conversations')->group(function () {

    // interaction types
    Route::get('/types', [ConversationTypeController::class, 'index']);
    Route::post('/types', [ConversationTypeController::class, 'store']);
    Route::put('/types/{id}', [ConversationTypeController::class, 'update']);
    Route::delete('/types/{id}', [ConversationTypeController::class, 'destroy']);

    // customer modes
    Route::get('/modes', [CustomerModeController::class, 'index']);
    Route::post('/modes', [CustomerModeController::class, 'store']);
    Route::put('/modes/{id}', [CustomerModeController::class, 'update']);
    Route::delete('/modes/{id}', [CustomerModeController::class, 'destroy']);

    Route::apiResource('types', ConversationTypeController::class);

    // Route::apiResource('customer-modes', CustomerModeController::class)
    //     ->except(['show']);

    // Route::apiResource('wrap-up/sub', WrapUpSubConversationController::class)
    //     ->except(['show']);

});
