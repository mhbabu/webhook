<?php

use App\Http\Controllers\Api\Webhook\EmailController;
use App\Http\Controllers\Api\Webhook\PlatformWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('webhook/whatsapp', [PlatformWebhookController::class, 'verifyWhatsAppToken']); // webhook verification for wahtsapp
Route::post('webhook/whatsapp', [PlatformWebhookController::class, 'incomingWhatsAppMessage']); // receive whatsapp webhook incoming message

Route::get('webhook/messenger', [PlatformWebhookController::class, 'verifyMessengerToken']); //  messenger token verification
Route::post('webhook/messenger', [PlatformWebhookController::class, 'incomingMessengerMessage']); // receive messenger webhook response

Route::get('webhook/instagram', [PlatformWebhookController::class, 'verifyInstagram']); // For for webhook verification
Route::post('webhook/instagram', [PlatformWebhookController::class, 'receiveInstagramMessage']); // POST for message reception

Route::get('webhook/facebook-page', [PlatformWebhookController::class, 'verifyFacebookPageToken']); //  facebook page token verification
Route::post('webhook/facebook-page', [PlatformWebhookController::class, 'receiveFacebookPageEventData']); // receive facebook page event data

Route::post('webhook/website', [PlatformWebhookController::class, 'receiveWebsitePageData'])->middleware('customer.token'); // receive website message data

// Route::get('webhook/instagram', [PlatformWebhookController::class, 'verifyIntragram']); // For for webhook verification
// Route::post('webhook/instagram', [PlatformWebhookController::class, 'receiveInstragramMsg']); // POST for message reception
Route::post('webhook/send-email', [EmailController::class, 'send']);
Route::post('webhook/receive-email', [EmailController::class, 'receive']);
Route::post('webhook/test-connection', [EmailController::class, 'testGmailImapConnection']);

require __DIR__.'/platform.php';

Route::post('/auth-user-broadcasting', function (Request $request) {
    $user = Auth::user(); // Already authenticated via middleware

    $socketId = $request->input('socket_id');
    $channelName = $request->input('channel_name');

    $appKey = env('REVERB_APP_KEY');
    $appSecret = env('REVERB_APP_SECRET');

    $stringToSign = $socketId.':'.$channelName;
    $signature = hash_hmac('sha256', $stringToSign, $appSecret);

    return response()->json([
        'auth' => $appKey.':'.$signature,
    ]);
})->middleware('auth:sanctum');
