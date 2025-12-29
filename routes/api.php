<?php

// require base_path('routes/webhook/webhook.php');

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require base_path('routes/webhook/webhook.php');
    require base_path('routes/user/user.php');
    require base_path('routes/message/message.php');
    require base_path('routes/customer/customer.php');
    require base_path('routes/dashboard/dashboard.php');
    require base_path('routes/report/report.php');
    require base_path('routes/system-setting/system-setting.php');
    require base_path('routes/thread/comment-thread.php');
});
