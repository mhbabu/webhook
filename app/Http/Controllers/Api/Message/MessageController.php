<?php

namespace App\Http\Controllers\Api\Message;

use App\Events\SocketIncomingMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Message\EndConversationRequest;
use App\Http\Requests\Message\SendWhatsAppMessageRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\Message\ConversationResource;
use App\Http\Resources\Message\MessageResource;
use App\Http\Resources\User\UserResource;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Platform;
use App\Models\User;
use App\Services\Platforms\WhatsAppService;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{

    public function agentConversationList(Request $request)
    {
        $agentId = auth()->id(); // authenticated agent
        $data    = $request->all();

        $pagination = !isset($data['pagination']) || $data['pagination'] === 'true';
        $page       = $data['page'] ?? 1;
        $perPage    = $data['per_page'] ?? 10;
        $query      = Conversation::with(['customer', 'agent', 'lastMessage'])->where('agent_id', $agentId)->latest();
        $isEnded    = isset($data['is_ended']) &&  $data['is_ended'] === 'true' ? true : false;

        if ($isEnded) {
            $query->whereNotNull('end_at'); // end conversation
        }else{
            $query->whereNull('end_at');
        }

        $query->latest();

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
        $agentId             = isset($data['agentId']) ? (int)$data['agentId'] : null;
        $agentAvailableScope = $data['availableScope'] ?? null;
        $source              = strtolower($data['source'] ?? '');
        $conversationId      = $data['messageData']['conversationId'] ?? null;

        // Validate required fields
        if (!$conversationId || !$agentId) {
            return jsonResponse('Missing required fields: conversationId or agentId.', false, null, 400);
        }

        DB::beginTransaction();
        try {
            // Fetch conversation

            $conversation = Conversation::find((int)$conversationId);
            // Log::info('[IncomingMsg] Fetched conversation', ['conversation' => $conversation,  'agentId' => $agentId]);
            $conversation->agent_id = $agentId;
            $conversation->save();

            // Update agent's current limit
            $user = User::find($agentId);
            $user->current_limit = $agentAvailableScope;
            $user->save();


            $message = Message::find($conversation->last_message_id);
            $message->receiver_id  = $agentId;
            $message->receiver_type = User::class;
            $message->save();

            Log::info('[Message Data] Updated message', ['message' => $message, 'receiver_id' => $message->receiver_id, 'agentId' => $agentId]);

            DB::commit();

            // Broadcast payload
            $payload = [
                'conversation' => new ConversationResource($conversation),
                'message'     => $conversation->lastMessage ? new MessageResource($conversation->lastMessage) : null,
            ];
            $channelData = [
                'platform' => $source,
                'agentId'  => $agentId,
            ];

            SocketIncomingMessage::dispatch($payload, $channelData);
            // Log::info('[IncomingMsg] Payload dispatched to socket', ['payload' => $payload, 'channelData' => $channelData]);

            return jsonResponse('Message received successfully.', true, null);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[IncomingMsg] Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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

    public function sendWhatsAppMessage(SendWhatsAppMessageRequest $request)
    {
        $data = $request->validated();

        $conversation = Conversation::findOrFail($data['conversation_id']);

        // Save message in DB
        $message = new Message();
        $message->conversation_id = $conversation->id;
        $message->sender_id = auth()->id();
        $message->sender_type = User::class;
        $message->receiver_type = Customer::class;
        $message->receiver_id = $conversation->customer_id;
        $message->type = 'text';
        $message->content = $data['content'];
        $message->direction = 'outgoing';
        $message->save();

        $customer = Customer::findOrFail($conversation->customer_id);
        $phone = $customer->phone;

        // Format phone number
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        $whatsAppService = new WhatsAppService();

        // Check if customer messaged in last 24h
        $lastIncomingMessage = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'incoming')
            ->latest()
            ->first();

        $within24Hours = $lastIncomingMessage && now()->diffInHours($lastIncomingMessage->created_at) <= 24;

        if ($within24Hours) {
            // ✅ Send free text
            $response = $whatsAppService->sendTextMessage($phone, $data['content']);
        } else {
            // ❌ Outside 24h: send fallback template
            $fallbackTemplateName = 'fallback_message'; // Replace with your actual template name
            $response = $whatsAppService->sendTemplateMessage($phone, $fallbackTemplateName, [$customer->name ?? 'Customer']);
        }

        return jsonResponse('WhatsApp message sent successfully.', true, [
            'message' => new MessageResource($message),
            'whatsapp_response' => $response,
            'used_template' => !$within24Hours
        ]);
    }
}
