<?php

use App\Http\Controllers\Api\Conversation\CustomerModeController;
use App\Http\Controllers\Api\Conversation\InteractionTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('/conversations')->group(function () {

    // categories
    Route::get('/types', [InteractionTypeController::class, 'index']);
    Route::post('/types', [InteractionTypeController::class, 'store']);
    Route::put('/types/{id}', [InteractionTypeController::class, 'update']);
    Route::delete('/types/{id}', [InteractionTypeController::class, 'destroy']);

    // sub-categories
    Route::get('/types/{id}/modes', [CustomerModeController::class, 'index']);
    Route::post('/modes', [CustomerModeController::class, 'store']);
    Route::put('/modes/{id}', [CustomerModeController::class, 'update']);
    Route::delete('/modes/{id}', [CustomerModeController::class, 'destroy']);
});
