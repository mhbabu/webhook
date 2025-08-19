<?php

use App\Http\Controllers\Api\User\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('user')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('roles', UserRoleController::class);
    Route::delete('roles/{role}', [UserRoleController::class, 'destroy']);
});
