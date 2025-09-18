<?php

use App\Http\Controllers\Api\Message\MessageController;
use App\Http\Controllers\Api\Message\QuickReplyController;
use App\Http\Controllers\Api\Message\UserQuickReplyController;
use App\Http\Controllers\Api\Message\WrapUpConversationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    Route::apiResource('user-quick-replies', UserQuickReplyController::class);
    Route::delete('user-quick-replies/{id}', [UserQuickReplyController::class, 'destroy']);

    Route::apiResource('quick-replies', QuickReplyController::class);
    Route::delete('quick-replies/{id}', [QuickReplyController::class, 'destroy']);
    Route::post('end-conversation', [MessageController::class, 'endConversation']);

    Route::delete('wrap-up-conversations/{id}', [WrapUpConversationController::class, 'destroy']);
    Route::apiResource('wrap-up-conversations', WrapUpConversationController::class);
});

  Route::post('incoming/messages', [MessageController::class, 'incomingMsg']);