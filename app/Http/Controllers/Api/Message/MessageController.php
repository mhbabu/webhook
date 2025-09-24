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
use Illuminate\Support\Facades\DB;
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
        $conversation = Conversation::with(['customer', 'agent', 'lastMessage', 'wrapUp'])->findOrFail($conversationId);
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

        $agentId             = $data['agentId'];
        $agentAvailableScope = $data['availableScope'];
        $source              = strtolower($data['source']);
        $conversationId      = $data['messageData']['conversationId'];

        if (!$conversationId) {
            return jsonResponse('Missing required field: conversationId.', false, null, 400);
        }

        DB::beginTransaction();
        try {
            $conversation = Conversation::findOrFail((int)$conversationId);
            $conversation->agent_id = $agentId;
            $conversation->save();

             Log::info('Conversation Record ' . json_encode($conversation));

            $message = Message::findOrFail($conversation->last_message_id);
            $message->receiver_id = $agentId;
            $message->save();

            $user = User::findOrFail($agentId);
            $user->current_limit = $agentAvailableScope;
            $user->save();

            // Broadcast payload
            $payload = [
                'conversation' => new ConversationResource($conversation),
                'messages'     => new MessageResource($message),
            ];

            $channelData = [
                'platform' => $source,
                'agentId'  => $agentId,
            ];

            Log::info('Payload: ' . json_encode($payload));

            SocketIncomingMessage::dispatch($payload, $channelData);

            DB::commit();
            return jsonResponse('Message received successfully.', true, null);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Incoming message error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return jsonResponse('Something went wrong while processing the message.', false, null, 500);
        }
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
