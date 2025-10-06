<?php

use App\Http\Controllers\Api\Webhook\WebhookController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

Route::get('webhook/whatsapp', [WebhookController::class, 'verifyWhatsAppToken']); // webhook verification for wahtsapp
Route::post('webhook/whatsapp', [WebhookController::class, 'incomingWhatsAppMessage']); // receive whatsapp webhook incoming message
Route::get('/whatsapp-media/{mediaId}', [WebhookController::class, 'fetchWhatsappMedia']);


// Route::get('webhook/instagram', [WebhookController::class, 'verifyIntragram']); // For for webhook verification
// Route::post('webhook/instagram', [WebhookController::class, 'receiveInstragramMsg']); // POST for message reception

// Route::get('webhook/messenger', [WebhookController::class, 'verifyMessenger']); // For for webhook verification
// Route::post('webhook/messenger', [WebhookController::class, 'receiveMessengerMsg']); // POST for message reception

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