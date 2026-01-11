<?php

use App\Http\Controllers\Api\Webhook\EmailWebhookController;
use App\Http\Controllers\Api\Webhook\FacebookWebhookController;
use App\Http\Controllers\Api\Webhook\InstagramWebhookController;
use App\Http\Controllers\Api\Webhook\PlatformWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('webhook/whatsapp', [PlatformWebhookController::class, 'verifyWhatsAppToken']); // webhook verification for wahtsapp
Route::post('webhook/whatsapp', [PlatformWebhookController::class, 'incomingWhatsAppMessage']); // receive whatsapp webhook incoming message

Route::get('webhook/facebook', [FacebookWebhookController::class, 'verifyFacebookToken']); //  messenger token verification
Route::post('webhook/facebook', [FacebookWebhookController::class, 'incomingFacebookEvent']); // receive messenger webhook response
// Route::post('webhook/facebook', [FacebookWebhookController::class, 'handle']);

Route::get('webhook/facebookPage', [FacebookWebhookController::class, 'verifyFacebookPageToken']);
Route::post('webhook/facebookPage', [FacebookWebhookController::class, 'webhook']); // receive facebook page event data new and improved

Route::get('webhook/instagram', [InstagramWebhookController::class, 'verifyInstagram']); // For for webhook verification
// Route::post('webhook/instagram', [InstagramWebhookController::class, 'receiveInstagramMessage']); // POST for message reception
Route::post('webhook/instagram', [InstagramWebhookController::class, 'receiveInstagramWebhook']); // Receive webhook enents for instagram

Route::post('webhook/website', [PlatformWebhookController::class, 'receiveWebsitePageData'])->middleware('customer.token'); // receive website message data

Route::post('webhook/receive-email', [EmailWebhookController::class, 'receiveEmailData']);
// Route::get('attachments/{attachment}/download', [PlatformWebhookController::class, 'download'])->name('attachments.download');

Route::post('social-pages/facebook/comments/webhook', [FacebookWebhookController::class, 'replyToCommentWebhook']);

require __DIR__.'/platform.php';
