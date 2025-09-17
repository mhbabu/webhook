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

        $agentId        = $data['agentId'] ?? null;
        $source         = $data['source'] ?? null;
        $mobile         = $data['messageData']['sender'] ?? null;
        $conversationId = $data['messageData']['conversationId'] ?? null;

        if (!$agentId || !$source || !$mobile) {
            return jsonResponse('Missing required fields: agentId, source, or sender phone.', false, null, 400);
        }

        $normalizedMobile = substr($mobile, -11);

        $user       = $request->user(); // 👈 the authenticated user
        $platformId = Platform::whereRaw('LOWER(name) = ?', [strtolower($source)])->value('id');
        $customer   = Customer::where('platform_id', $platformId)->where('phone', $normalizedMobile)->first();

        $payload = [
            'auth_user_id'   => $user->id,
            'user'           => $agentId ? new UserResource(User::find($agentId)) : null,
            'customer'       => $customer ? new CustomerResource($customer) : null,
            'platform'       => $source,
            'agentId'        => $agentId,
            'conversationId' => $conversationId,
            'sender'         => $normalizedMobile,
            'message'        => $data['messageData']['message'] ?? null,
        ];

        SocketIncomingMessage::dispatch($payload);

        return jsonResponse('Message received successfully.', true, null);
    }
}
