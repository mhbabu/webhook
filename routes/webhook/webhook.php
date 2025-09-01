<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('webhook/whatsapp', [WebhookController::class, 'verify']); // GET for webhook verification
Route::post('webhook/whatsapp', [WebhookController::class, 'whatsapp']); // POST for message reception
Route::get('/whatsapp-media/{mediaId}', [WebhookController::class, 'fetchWhatsappMedia']);


// Route::get('webhook/instagram', [WebhookController::class, 'verifyIntragram']); // For for webhook verification
// Route::post('webhook/instagram', [WebhookController::class, 'receiveInstragramMsg']); // POST for message reception

// Route::get('webhook/messenger', [WebhookController::class, 'verifyMessenger']); // For for webhook verification
// Route::post('webhook/messenger', [WebhookController::class, 'receiveMessengerMsg']); // POST for message reception

require __DIR__ .'/platform.php';