<?php

use App\Http\Controllers\Api\Message\MessageController;
use App\Http\Controllers\Api\Message\QuickReplyController;
use App\Http\Controllers\Api\Message\UserQuickReplyController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    Route::apiResource('user-quick-replies', UserQuickReplyController::class);
    Route::delete('user-quick-replies/{id}', [UserQuickReplyController::class, 'destroy']);

    Route::apiResource('quick-replies', QuickReplyController::class);
    Route::delete('quick-replies/{id}', [QuickReplyController::class, 'destroy']);
    // Route::post('incoming/messages', [MessageController::class, 'incomingMsg']);
});

// Route::post('incoming/messages', [MessageController::class, 'incomingMsg']);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;

Route::post('/broadcasting/auth', function (Request $request) {
    // Sanctum automatically checks the token via middleware
    if (!Auth::guard('sanctum')->check()) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    // Let Laravel handle the complex signature generation
    return Broadcast::auth($request);
})->middleware('auth:sanctum');

// Route::post('/broadcasting/auth', function (Request $request) {
//     // Here you must authenticate the user first (e.g., via Sanctum token)
//     $user = Auth::guard('sanctum')->user();
//     if (!$user) {
//         return response()->json(['message' => 'Unauthenticated.'], 401);
//     }

//     $socketId = $request->input('socket_id');
//     $channelName = $request->input('channel_name');

//     // Generate the signature with your Reverb app secret
//     $appKey = env('REVERB_APP_KEY');
//     $appSecret = env('REVERB_APP_SECRET');

//     $stringToSign = $socketId . ':' . $channelName;
//     $signature = hash_hmac('sha256', $stringToSign, $appSecret);

//     return response()->json([
//         'auth' => $appKey . ':' . $signature
//     ]);
// })->middleware('auth:sanctum');

Route::post('incoming/messages', [MessageController::class, 'incomingMsg']);
