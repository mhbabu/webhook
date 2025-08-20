<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Message\QuickReplyController;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('quick-replies', QuickReplyController::class);
    Route::delete('quick-replies/{id}', [QuickReplyController::class, 'destroy']);
});
