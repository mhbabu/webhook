<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;

Route::get('inbound/whatapp/message', [WebhookController::class, 'verify']); // GET for webhook verification
Route::post('inbound/whatapp/message', [WebhookController::class, 'whatsapp']); // POST for message reception

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
