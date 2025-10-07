<?php

use App\Http\Controllers\Api\Webhook\PlatformWebhookController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

Route::get('webhook/whatsapp', [PlatformWebhookController::class, 'verifyWhatsAppToken']); // webhook verification for wahtsapp
Route::post('webhook/whatsapp', [PlatformWebhookController::class, 'incomingWhatsAppMessage']); // receive whatsapp webhook incoming message

Route::get('webhook/messenger', [PlatformWebhookController::class, 'verifyMessengerToken']); // For for webhook verification
Route::post('webhook/messenger', [PlatformWebhookController::class, 'incomingMessengerMessage']); // POST for message reception


// Route::get('webhook/instagram', [PlatformWebhookController::class, 'verifyIntragram']); // For for webhook verification
// Route::post('webhook/instagram', [PlatformWebhookController::class, 'receiveInstragramMsg']); // POST for message reception



require __DIR__ .'/platform.php';

Route::post('/auth-user-broadcasting', function (Request $request) {
    $user = Auth::user(); // Already authenticated via middleware

    $socketId = $request->input('socket_id');
    $channelName = $request->input('channel_name');

    $appKey = env('REVERB_APP_KEY');
    $appSecret = env('REVERB_APP_SECRET');

    $stringToSign = $socketId . ':' . $channelName;
    $signature = hash_hmac('sha256', $stringToSign, $appSecret);

    return response()->json([
        'auth' => $appKey . ':' . $signature
    ]);
})->middleware('auth:sanctum');