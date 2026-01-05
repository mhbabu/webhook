<?php

// require base_path('routes/webhook/webhook.php');

use App\Http\Controllers\Api\Webhook\FacebookPostMockController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Route::get('social-pages/conversation', [FacebookPostMockController::class, 'socialPageConversations']);
    // Route::get('social-pages/conversation/{conversationId}',[FacebookPostMockController::class, 'details']);
    // Route::get('facebook-posts',[FacebookPostMockController::class, 'index']);

    require base_path('routes/webhook/webhook.php');
    require base_path('routes/user/user.php');
    require base_path('routes/message/message.php');
    require base_path('routes/customer/customer.php');
    require base_path('routes/dashboard/dashboard.php');
    require base_path('routes/report/report.php');
    require base_path('routes/system-setting/system-setting.php');
    require base_path('routes/thread/thread.php');
});
