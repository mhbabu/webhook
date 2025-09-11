<?php

namespace App\Http\Controllers\Api\Message;

use App\Events\SocketIncomingMessage;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\User\UserResource;
use App\Models\Customer;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    public function incomingMsg(Request $request)
    {
        $data = $request->all();
        Log::info('Incoming request data: ' . json_encode($request->all()));

        // Extract required fields
        $agentId        = $data['agentId'] ?? null;
        $source         = $data['source'] ?? null;
        $mobile         = $data['messageData']['sender'] ?? null;
        $conversationId = $data['messageData']['conversationId'] ?? null;

        // Validate required fields
        if (!$agentId || !$source || !$mobile) {
            return jsonResponse('Missing required fields: agentId, source, or sender phone.', false, null, 400);
        }

        // Normalize phone number: get last 11 digits
        $normalizedMobile = substr($mobile, -11);

        // Fetch User by agentId
        $user = User::find($agentId);

        // Fetch Platform ID
        $platformId = Platform::whereRaw('LOWER(name) = ?', [strtolower($source)])->value('id');

        // Fetch Customer
        $customer = Customer::where('platform_id', $platformId)->where('phone', $normalizedMobile)->first();

        // Build response data
        $data = [
            'user'     => $user ? new UserResource($user) : null,
            'customer' => $customer ? new CustomerResource($customer) : null,
            'platform' => $source
        ];

        SocketIncomingMessage::dispatch($data);
        return jsonResponse('Message received successfully.', true, null);
    }
}
