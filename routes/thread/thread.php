<?php

use App\Http\Controllers\Api\Threads\ThreadController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('agent/threads', [ThreadController::class, 'agentThreadList']);
    // Route::get('threads/{platform}/{platform_comment_id}', [ThreadController::class, 'getConversationWiseThread']);
    Route::get('threads/{Conversation}', [ThreadController::class, 'getConversationWiseThread']);
});
