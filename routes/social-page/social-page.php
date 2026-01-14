<?php

use App\Http\Controllers\Api\SocialPage\FacebookPageController;
use App\Http\Controllers\Api\SocialPage\FacebookPagePostSyncController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('social-pages/conversation', [FacebookPageController::class, 'getAgentConversationList']);
    Route::get('social-pages/conversation/{conversationId}', [FacebookPageController::class, 'conversationWisePostDetails']);
    Route::post('social-pages/comment-reply/{conversationId}', [FacebookPageController::class, 'replyAComment']);
});

Route::get('sync-social-post-data', [FacebookPagePostSyncController::class, 'syncSocialPostData']);
