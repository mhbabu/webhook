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

    Route::get('conversations', [MessageController::class, 'agentConversationList']);
    Route::get('conversation/{conversation}/messages', [MessageController::class, 'getConversationWiseMessages']);
    // Route::post('whatsapp/message/send', [MessageController::class, 'sendWhatsAppMessageFromAgent']);
    // Route::post('messenger/message/send', [MessageController::class, 'sendMessagerMessageFromAgent']);
    Route::post('message/send', [MessageController::class, 'sendAgentMessageToCustomer']);

    // Route::post('conversations/{conversation}/messages/mark-read', [MessageController::class, 'markAsRead']);
});

Route::post('incoming/messages', [MessageController::class, 'incomingMsg'])->withoutMiddleware('auth:sanctum');
