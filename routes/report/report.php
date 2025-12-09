<?php

use App\Http\Controllers\Api\Report\ReportController;
use Illuminate\Support\Facades\Route;

// Route::prefix('report')->middleware(['auth:sanctum'])->group(function () { //->middleware('auth:sanctum')
Route::prefix('report')->group(function () { //->middleware('auth:sanctum')
    Route::get('/conversations', [ReportController::class, 'conversationReport']);
    Route::get('/conversations/{conversation}', [ReportController::class, 'conversationDetails']);
});
