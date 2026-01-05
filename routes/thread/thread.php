<?php

use App\Http\Controllers\Api\Threads\ThreadController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Route::get('agent/threads', [ThreadController::class, 'agentThreadList']);
    // Route::get('threads/{Conversation}', [ThreadController::class, 'getConversationWiseThread']);
    Route::get('social-pages/conversation', [ThreadController::class, 'agentThreadList']);
    Route::get('social-pages/conversation/{conversationId}', [ThreadController::class, 'getConversationWiseThread']);

    // Route::get('facebook-posts', [FacebookPostMockController::class, 'index']);
    // Route::get('social-pages/conversation', [ThreadController::class, 'agentThreadList']);
    // Route::get('threads/{Conversation}', [ThreadController::class, 'getConversationWiseThread']);
});
