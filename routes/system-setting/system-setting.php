<?php 

use App\Http\Controllers\Api\SystemSetting\SystemSettingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:Super Admin,Admin,Supervisor'])->group(function () { //->middleware('auth:sanctum')
   Route::apiResource('system-settings', SystemSettingController::class);
});
