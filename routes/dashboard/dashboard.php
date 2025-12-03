<?php

use App\Http\Controllers\Api\Dashboard\DashboardController;
use Illuminate\Support\Facades\Route;


Route::prefix('dashboard')->group(function () {
    Route::get('/health', function () {
        return response()->json(['status' => 'ok']);
    })->name('dashboard.health');
    Route::get('agent-status', [DashboardController::class, 'agentStatus'])->name('dashboard.agent-status');
    Route::get('pending', [DashboardController::class, 'pending'])->name('dashboard.pending');
});
