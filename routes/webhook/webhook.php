<?php

use App\Http\Controllers\Api\Webhook\FacebookWebhookController;
use App\Http\Controllers\Api\Webhook\InstagramWebhookController;
use App\Http\Controllers\Api\Webhook\PlatformWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('webhook/whatsapp', [PlatformWebhookController::class, 'verifyWhatsAppToken']); // webhook verification for wahtsapp
Route::post('webhook/whatsapp', [PlatformWebhookController::class, 'incomingWhatsAppMessage']); // receive whatsapp webhook incoming message

Route::get('webhook/facebook', [FacebookWebhookController::class, 'verifyFacebookToken']); //  messenger token verification
// Route::post('webhook/facebook', [FacebookWebhookController::class, 'incomingFacebookEvent']); // receive messenger webhook response
// Route::post('webhook/facebook', [FacebookWebhookController::class, 'handle']);
Route::post('webhook/facebook', [FacebookWebhookController::class, 'webhook']); // receive facebook page event data new and improved

Route::get('webhook/instagram', [InstagramWebhookController::class, 'verifyInstagram']); // For for webhook verification
Route::post('webhook/instagram', [InstagramWebhookController::class, 'receiveInstagramMessage']); // POST for message reception

Route::post('webhook/website', [PlatformWebhookController::class, 'receiveWebsitePageData'])->middleware('customer.token'); // receive website message data

Route::post('webhook/receive-email', [PlatformWebhookController::class, 'receiveEmailData']);
// Route::get('attachments/{attachment}/download', [PlatformWebhookController::class, 'download'])->name('attachments.download');

require __DIR__.'/platform.php';
