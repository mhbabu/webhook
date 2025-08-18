<?php

use App\Http\Controllers\Api\Webhook\PlatformController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:Super Admin,Admin,Supervisor'])->group(function () {
    Route::apiResource('platforms', PlatformController::class)->except(['edit', 'create']);
    Route::delete('platforms/{platform}', [PlatformController::class, 'destroy']);
});
