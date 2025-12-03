<?php

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

Route::post('webhook/receive-email', [PlatformWebhookController::class, 'receiveEmailData']);
Route::get('/attachments/{attachment}/download', [PlatformWebhookController::class, 'download'])->name('attachments.download');

require __DIR__.'/platform.php';

