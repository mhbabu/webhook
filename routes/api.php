<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;

Route::get('webhook/whatapp', [WebhookController::class, 'verify']); // GET for webhook verification
Route::post('webhook/whatapp', [WebhookController::class, 'whatsapp']); // POST for message reception
Route::get('/whatsapp-media/{mediaId}', [WebhookController::class, 'fetchWhatsappMedia']);


Route::get('webhook/instagram', [WebhookController::class, 'verifyIntragram']); // For for webhook verification
Route::post('webhook/instagram', [WebhookController::class, 'receiveInstragramMsg']); // POST for message reception

Route::get('webhook/messenger', [WebhookController::class, 'verifyMessenger']); // For for webhook verification
Route::post('webhook/messenger', [WebhookController::class, 'receiveMessengerMsg']); // POST for message reception

Route::post('user/register', [AuthController::class, 'register']);
Route::post('user/login', [AuthController::class, 'login']);
Route::post('user/password/reset-request', [AuthController::class, 'passwordResetRequest']);
Route::post('user/reset-password', [AuthController::class, 'resetPassword']);

// Routes requiring authentication
Route::middleware('auth:api')->group(function () {
    Route::post('user/logout', [AuthController::class, 'logout']);
    Route::post('user/update-password', [AuthController::class, 'updatePassword']);
    // Route::post('user/update-profile', [AuthController::class, 'updateProfile']);
});
