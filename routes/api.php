<?php


// require base_path('routes/webhook/webhook.php');

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require base_path('routes/webhook/webhook.php');
    require base_path('routes/user/user.php');
    require base_path('routes/webhook/webhook.php');
    require base_path('routes/message/message.php');

    Route::post('channel-test', [WebhookController::class, 'websocketTestMethod']);
});
