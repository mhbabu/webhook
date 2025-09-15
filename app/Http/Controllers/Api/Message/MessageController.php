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
        Log::info('Incoming request data: ' . json_encode($data));

        $agentId        = $data['agentId'] ?? null;
        $source         = $data['source'] ?? null;
        $mobile         = $data['messageData']['sender'] ?? null;
        $conversationId = $data['messageData']['conversationId'] ?? null;

        if (!$agentId || !$source || !$mobile) {
            return jsonResponse('Missing required fields: agentId, source, or sender phone.', false, null, 400);
        }

        $normalizedMobile = substr($mobile, -11);

        $user       = User::find($agentId);
        $platformId = Platform::whereRaw('LOWER(name) = ?', [strtolower($source)])->value('id');
        $customer   = Customer::where('platform_id', $platformId)->where('phone', $normalizedMobile)->first();

        $payload = [
            'user'        => $user ? new UserResource($user) : null,
            'customer'    => $customer ? new CustomerResource($customer) : null,
            'platform'    => $source,
            'agentId'     => $agentId, // 👈 needed for dynamic channel
        ];

        Log::info('Dispatching SocketIncomingMessage with payload: ' . json_encode($payload));

        SocketIncomingMessage::dispatch($payload);

        return jsonResponse('Message received successfully.', true, null);
    }
}
