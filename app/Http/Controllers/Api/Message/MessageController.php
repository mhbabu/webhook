<?php

namespace App\Http\Controllers\Api\Message;

use App\Events\SocketIncomingMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Message\EndConversationRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\Message\ConversationResource;
use App\Http\Resources\Message\MessageResource;
use App\Http\Resources\User\UserResource;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{

    public function agentConversationList(Request $request)
    {
        $agentId = auth()->id(); // authenticated agent
        $data = $request->all();

        $pagination = !isset($data['pagination']) || $data['pagination'] === 'true';
        $page       = $data['page'] ?? 1;
        $perPage    = $data['per_page'] ?? 10;
        $query      = Conversation::with(['customer', 'agent', 'lastMessage'])->where('agent_id', $agentId)->latest();

        if ($pagination) {
            $conversations = $query->paginate($perPage, ['*'], 'page', $page);
            return jsonResponseWithPagination('Conversations retrieved successfully', true, ConversationResource::collection($conversations)->response()->getData(true));
        }

        $conversations = $query->get();

        return jsonResponse('Conversations retrieved successfully', true, ConversationResource::collection($conversations));
    }

    public function getConversationWiseMessages(Request $request, $conversationId)
    {
        $data         = $request->all();
        $pagination   = !isset($data['pagination']) || $data['pagination'] === 'true';
        $page         = $data['page'] ?? 1;
        $perPage      = $data['per_page'] ?? 10;
        $conversation = Conversation::with(['customer', 'agent', 'lastMessage'])->findOrFail($conversationId);
        $query = Message::with(['sender', 'receiver'])->where('conversation_id', $conversation->id)->where(function ($q) {
            $q->where('sender_id', auth()->id())->orWhere('receiver_id', auth()->id());
        })->latest();


        if ($pagination) {
            $messages = $query->paginate($perPage, ['*'], 'page', $page);

            // Reverse for UI if needed
            $reversed = $messages->getCollection()->reverse()->values();
            $messages->setCollection($reversed);

            return jsonResponseWithPagination(
                'Conversation messages retrieved successfully',
                true,
                [
                    'conversation' => new ConversationResource($conversation),
                    'messages'     => MessageResource::collection($messages),
                    'pagination'   => [
                        'current_page' => $messages->currentPage(),
                        'per_page'     => $messages->perPage(),
                        'total'        => $messages->total(),
                        'last_page'    => $messages->lastPage(),
                    ]
                ]
            );
        }

        $allMessages = $query->get()->reverse()->values();

        return jsonResponse('Conversation messages retrieved successfully', true, ['conversation' => new ConversationResource($conversation), 'messages' => MessageResource::collection($allMessages)]);
    }

    public function incomingMsg(Request $request)
    {
        $data = $request->all();

        Log::info('Incoming message data: ' . json_encode($data));

        $agentId        = $data['agentId'] ?? null;
        $source         = $data['messageData']['source'] ?? null;
        $mobile         = $data['messageData']['sender'] ?? null;
        $conversationId = $data['messageData']['conversationId'] ?? null;

        if (!$agentId || !$source || !$mobile) {
            return jsonResponse('Missing required fields: agentId, source, or sender phone.', false, null, 400);
        }

        $normalizedMobile = substr($mobile, -11);
        info("Normalized Mobile: $normalizedMobile");
        $platformId = Platform::whereRaw('LOWER(name) = ?', [strtolower($source)])->value('id');
        $customer   = Customer::where('platform_id', $platformId)->where('phone', $normalizedMobile)->first();

        $payload = [
            'user'           => $agentId ? new UserResource(User::find($agentId)) : null,
            'customer'       => $customer ? new CustomerResource($customer) : null,
            'platform'       => $source,
            'agentId'        => $agentId,
            'conversationId' => $conversationId,
            'sender'         => $normalizedMobile,
            'message'        => $data['messageData']['message'] ?? null,
        ];

        Log::info('Payload: ' . json_encode($payload));
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

        if ($conversation->end_at) {
            return jsonResponse('Conversation already ended.', false, null, 400);
        }

        $conversation->end_at = now();
        $conversation->wrap_up_id = $data['wrap_up_id'];
        $conversation->ended_by = $user->id;
        $conversation->save();

        return jsonResponse('Conversation ended successfully.', true, null);
    }
}
