<?php

use App\Http\Controllers\Api\Report\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('report')->middleware(['auth:sanctum', 'role:Super Admin,Admin,Supervisor'])->group(function () { //->middleware('auth:sanctum')
    Route::get('/conversations', [ReportController::class, 'conversationReport']);
    Route::get('report/conversation/details', [ReportController::class, 'conversationDetails']);
});
