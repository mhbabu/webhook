<?php

namespace App\Http\Controllers\Api\Message;

use App\Events\SocketIncomingMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Message\EndConversationRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\User\UserResource;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    public function incomingMsg(Request $request)
    {
        $data = $request->all();

        Log::info('Incoming message data: ' . json_encode($data));

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

    public function endConversation(EndConversationRequest $request)
    {
        $user = User::find(auth()->id());
        $data = $request->validated();
        $conversation = Conversation::find($data['conversation_id']);

        if ($user->id !== $conversation->agent_id) {
            return jsonResponse('You are not authorized to end this conversation.', false, null, 403);
        }

        if($conversation->end_at) {
            return jsonResponse('Conversation already ended.', false, null, 400);
        }

        $conversation->end_at = now();
        $conversation->wrap_up_id = $data['wrap_up_id'];
        $conversation->ended_by = $user->id;
        $conversation->save();

        return jsonResponse('Conversation ended successfully.', true, null);
    }
}
