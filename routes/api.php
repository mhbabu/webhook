<?php

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

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
