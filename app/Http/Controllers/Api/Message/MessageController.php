<?php

namespace App\Http\Controllers\Api\Message;

use App\Events\SocketIncomingMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Message\EndConversationRequest;
use App\Http\Requests\Message\SendPlatformMessageRequest;
use App\Http\Resources\Message\ConversationInfoResource;
use App\Http\Resources\Message\ConversationResource;
use App\Http\Resources\Message\MessageResource;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use App\Services\Platforms\FacebookService;
use App\Services\Platforms\WhatsAppService;
use Illuminate\Http\Request;
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
        } else {
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
        Log::info('[IncomingMsg] Data', $data);
        $agentId             = isset($data['agentId']) ? (int)$data['agentId'] : null;
        $agentAvailableScope = $data['availableScope'] ?? null;
        $source              = strtolower($data['source'] ?? '');
        $conversationId      = $data['messageData']['conversationId'] ?? null;
        $messageId           = $data['messageData']['messageId'];
        $conversationType    = $data['messageData']['conversationType'];

        // Validate required fields
        if (!$conversationId || !$agentId) {
            info('yes your dout is true');
            return jsonResponse('Missing required fields: conversationId or agentId.', false, null, 400);
        }

        // DB::beginTransaction();
        // try {

        // Update agent's current limit
        $user = User::find($agentId);
        $user->current_limit = $agentAvailableScope;
        $user->save();
        Log::info('[UserData]' . json_encode($user));

        // Fetch conversation
        $conversation = Conversation::find((int)$conversationId);
        Log::info('[IncomingMsg] before conversation', ['conversation' => $conversation,  'agentId' => $agentId]);

        if ($conversationType === 'new') {
            $conversation->agent_id = $user->id;
            $conversation->save();
        }


        Log::info('[IncomingMsg] after conversation', ['conversation' => $conversation,  'agentId' => $agentId]);

        $convertedMsgId          = (int)$messageId;
        $message                 = Message::find($convertedMsgId);

        if ($conversationType === 'new') {
            $message->receiver_id    = $conversation->agent_id ?? $user->id;
            $message->receiver_type  = User::class;
            $message->save();
        }

        Log::info('[Message Data] Updated message', ['message' => $message, 'receiver_id' => $message->receiver_id, 'agentId' => $agentId]);

        // DB::commit();

        // Broadcast payload

        $payload = [
            'conversation' => new ConversationInfoResource($conversation, $message),
            'message'      => $message ? new MessageResource($message) : null,
        ];

        $channelData = [
            'platform' => $source,
            'agentId'  => $agentId,
        ];

        broadcast(new SocketIncomingMessage($payload, $channelData));
        // SocketIncomingMessage::dispatch($payload, $channelData);


        return jsonResponse('Message received successfully.', true, null);
        // } catch (\Exception $e) {
        //     DB::rollBack();
        //     Log::error('[IncomingMsg] Exception occurred',[
        //         'message' => $e->getMessage(),
        //         'trace' => $e->getTraceAsString(),
        //     ]);
        //     return jsonResponse('Something went wrong while processing the message.', false, null, 500);
        // }
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

    public function sendWhatsAppMessageFromAgent1(SendPlatformMessageRequest $request)
    {
        $data         = $request->validated();
        $conversation = Conversation::find($data['conversation_id']);
        $customer     = Customer::find($conversation->customer_id);
        $phone        = $customer->phone;

        // Save message in DB
        $message                    = new Message();
        $message->conversation_id   = $conversation->id;
        $message->sender_id         = auth()->id();
        $message->sender_type       = User::class;
        $message->receiver_type     = Customer::class;
        $message->receiver_id       = $conversation->customer_id;
        $message->type              = 'text';
        $message->content           = $data['content'];
        $message->direction         = 'outgoing';
        $message->save();

        $whatsAppService = new WhatsAppService();

        $mediaResponses = [];

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $storedPath = $file->store('wa_temp');
                $fullPath = storage_path("app/{$storedPath}");
                $mime = $file->getMimeType();

                // Upload to WhatsApp
                $mediaId = $whatsAppService->uploadMedia($fullPath, $mime);

                if ($mediaId) {
                    // Determine type
                    $mediaType = match (true) {
                        str_starts_with($mime, 'image/') => 'image',
                        str_starts_with($mime, 'video/') => 'video',
                        str_starts_with($mime, 'audio/') => 'audio',
                        str_starts_with($mime, 'application/') => 'document',
                        default => 'document',
                    };

                    $mediaResponse = $whatsAppService->sendMediaMessage($phone, $mediaId, $mediaType);
                    $mediaResponses[] = $mediaResponse;

                    // Optionally: save each media as message
                    Message::create([
                        'conversation_id'     => $conversation->id,
                        'sender_id'           => auth()->id(),
                        'sender_type'         => User::class,
                        'receiver_type'       => Customer::class,
                        'receiver_id'         => $conversation->customer_id,
                        'type'                => $mediaType,
                        'content'             => null,
                        'direction'           => 'outgoing',
                        'platform_message_id' => $mediaResponse['messages'][0]['id'] ?? null,
                        'parent_id'           => $data['parent_id'] ?? null,
                    ]);
                }
            }
        }

        // If text provided, send last (after media)
        $textResponse = null;
        if (!empty($data['content'])) {
            $textResponse = $whatsAppService->sendTextMessage($phone, $data['content']);
            $message->update(['platform_message_id' => $textResponse['messages'][0]['id']]);
        }

        return jsonResponse('WhatsApp message(s) sent successfully.', true, [
            'text_message'      => $message ? new MessageResource($message) : null,
            'media_responses'   => $mediaResponses,
            'text_response'     => $textResponse,
        ]);
    }

    public function sendMessagerMessageFromAgent1(SendPlatformMessageRequest $request)
    {
        $data         = $request->validated();
        info(['$data' => $data]);
        $conversation = Conversation::find($data['conversation_id']);
        $customer     = Customer::find($conversation->customer_id);
        $recipientId  = $customer->platform_user_id; // make sure this field exists!

        $facebookService = new FacebookService();
        $mediaResponses  = [];

        // Save text message in DB first (for tracking platform_message_id later)

        $textMessage = Message::create([
            'conversation_id'   => $conversation->id,
            'sender_id'         => auth()->id(),
            'sender_type'       => User::class,
            'receiver_type'     => Customer::class,
            'receiver_id'       => $customer->id,
            'type'              => 'text',
            'content'           => $data['content'],
            'direction'         => 'outgoing',
            'platform'          => 'messenger',
        ]);

        // Handle file uploads (attachments)
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $storedPath = $file->store('messenger_temp', 'public');
                $fullPath   = "messenger_temp/" . basename($storedPath);
                $mime       = $file->getMimeType();

                // Send via Facebook API
                $response = $facebookService->sendAttachmentMessage($recipientId, $fullPath, $mime);
                $mediaResponses[] = $response;

                // Save media message to DB
                Message::create([
                    'conversation_id'     => $conversation->id,
                    'sender_id'           => auth()->id(),
                    'sender_type'         => User::class,
                    'receiver_type'       => Customer::class,
                    'receiver_id'         => $customer->id,
                    'type'                => $facebookService->resolveMediaType($mime),
                    'content'             => null,
                    'direction'           => 'outgoing',
                    'platform'            => 'messenger',
                    'platform_message_id' => $response['message_id'] ?? null,
                    'parent_id'           => $data['parent_id'] ?? null,
                ]);
            }
        }

        // Send the text message *after* media
        $textResponse = null;
        if ($textMessage) {
            $textResponse = $facebookService->sendTextMessage($recipientId, $textMessage->content);
            $textMessage->update([
                'platform_message_id' => $textResponse['message_id'] ?? null
            ]);
        }

        return jsonResponse('Messenger message(s) sent successfully.', true, [
            'text_message'    => $textMessage ? new MessageResource($textMessage) : null,
            'media_responses' => $mediaResponses,
            'text_response'   => $textResponse,
        ]);
    }

    /**
     * Send message from agent to customer across platforms (WhatsApp, Messenger).
     * Handles conversation creation/expiration and message with attachments.
     *
     * @param SendPlatformMessageRequest $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function sendAgentMessageToCustomer(SendPlatformMessageRequest $request)
    {
        $data = $request->validated();
        $agentId = auth()->id();

        // Step 1: Find conversation
        $conversation = Conversation::find($data['conversation_id']);

        if (! $conversation) {
            return jsonResponse('Conversation not found', false, [], 404);
        }

        // Step 2: Get customer from conversation
        $customer = Customer::find($conversation->customer_id);

        if (! $customer) {
            return jsonResponse('Customer not found', false, [], 404);
        }

        $platformName = strtolower($customer->platform->name);

        // Step 3: Check if conversation expired or ended
        // $expireHours = config('services.conversation_expire_hours');
        // $expired = $conversation->end_at !== null || $conversation->created_at->lt(now()->subHours($expireHours));

        // if ($expired) {
        //     // Create new conversation if expired
        //     $conversation = $this->getOrCreateConversationForAgentCustomer($customer->id, $agentId, $platformName);
        // }

        // Step 4: Extract attachments if any
        $attachments = $request->hasFile('attachments') ? $request->file('attachments') : [];

        Log::info('Agent sending message', [
            'requestData'      => $data,
            // 'attachmentsCount' => count($attachments),
            // 'attachements'      => $attachments,
        ]);

        // Step 5: Send based on platform
        if ($platformName === 'facebook') {
            return $this->sendMessagerMessageFromAgent($data, $attachments, $conversation, $customer);
        } elseif ($platformName === 'whatsapp') {
            return $this->sendWhatsAppMessageFromAgent($data, $attachments, $conversation, $customer);
        }

        return jsonResponse('Unsupported platform', false, [], 422);
    }

    protected function getOrCreateConversationForAgentCustomer(int $customerId, int $agentId, string $platformName): Conversation
    {
        $expireHours = config('services.conversation_expire_hours');
        $now         = now();

        // 1️⃣ Try to find an active conversation first
        $conversation = Conversation::where('customer_id', $customerId)
            ->where('agent_id', $agentId)
            ->where('platform', $platformName)
            ->where(function ($q) use ($now, $expireHours) {
                $q->whereNull('end_at')
                    ->orWhere('created_at', '>=', $now->subHours($expireHours));
            })
            ->latest()
            ->first();

        // 2️⃣ Create new conversation if none found or expired/ended
        if (!$conversation || $conversation->end_at !== null || $conversation->created_at < $now->subHours($expireHours)) {
            $conversation = Conversation::create([
                'customer_id' => $customerId,
                'agent_id'    => $agentId,
                'platform'    => $platformName,
                'trace_id'    => strtoupper(substr($platformName, 0, 2)) . '-' . now()->format('YmdHis') . '-' . uniqid(),
            ]);
        }

        return $conversation;
    }

    protected function sendWhatsAppMessageFromAgent(array $data, array $attachments, Conversation $conversation, Customer $customer)
    {
        $phone = $customer->phone;

        // Save text message in DB
        $message                      = new Message();
        $message->conversation_id     = $conversation->id;
        $message->sender_id           = auth()->id();
        $message->sender_type         = User::class;
        $message->receiver_type       = Customer::class;
        $message->receiver_id         = $customer->id;
        $message->type                = 'text';
        $message->content             = $data['content'] ?? '';
        $message->direction           = 'outgoing';
        // $message->platform            = 'whatsapp';
        $message->save();

        $whatsAppService = new WhatsAppService();

        $mediaResponses = [];

        foreach ($attachments as $file) {
            $storedPath = $file->store('wa_temp');
            $fullPath   = storage_path("app/{$storedPath}");
            $mime       = $file->getMimeType();

            // Upload media to WhatsApp
            $mediaId = $whatsAppService->uploadMedia($fullPath, $mime);

            if ($mediaId) {
                $mediaType = match (true) {
                    str_starts_with($mime, 'image/') => 'image',
                    str_starts_with($mime, 'video/') => 'video',
                    str_starts_with($mime, 'audio/') => 'audio',
                    str_starts_with($mime, 'application/') => 'document',
                    default => 'document',
                };

                $mediaResponse = $whatsAppService->sendMediaMessage($phone, $mediaId, $mediaType);
                $mediaResponses[] = $mediaResponse;

                // Save each media as message in DB
                Message::create([
                    'conversation_id'      => $conversation->id,
                    'sender_id'            => auth()->id(),
                    'sender_type'          => User::class,
                    'receiver_type'        => Customer::class,
                    'receiver_id'          => $customer->id,
                    'type'                 => $mediaType,
                    'content'              => null,
                    'direction'            => 'outgoing',
                    'platform'             => 'whatsapp',
                    'platform_message_id'  => $mediaResponse['messages'][0]['id'] ?? null,
                    'parent_id'            => $data['parent_id'] ?? null,
                ]);
            }
        }

        // Send text message after media
        $textResponse = null;
        if (!empty($data['content'])) {
            $textResponse = $whatsAppService->sendTextMessage($phone, $data['content']);
            $message->update(['platform_message_id' => $textResponse['messages'][0]['id'] ?? null]);
        }

        return jsonResponse('WhatsApp message(s) sent successfully.', true, [
            'text_message' => $message ? new MessageResource($message) : null,
            'media_responses' => $mediaResponses,
            'text_response' => $textResponse,
        ]);
    }

    protected function sendMessagerMessageFromAgent(array $data, array $attachments, Conversation $conversation, Customer $customer)
    {
        $recipientId = $customer->platform_user_id;
        $facebookService = new FacebookService();
        $mediaResponses = [];

        // Step 1: Save main text message
        $textMessage = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => auth()->id(),
            'sender_type'     => User::class,
            'receiver_type'   => Customer::class,
            'receiver_id'     => $customer->id,
            'type'            => 'text',
            'content'         => $data['content'] ?? '',
            'direction'       => 'outgoing',
            'platform'        => 'messenger',
        ]);

        // Step 2: Handle attachments
        foreach ($attachments as $file) {
            $storedPath = $file->store('messenger_temp', 'public');
            $mime = $file->getMimeType();

            // Send to Facebook
            $response = $facebookService->sendAttachmentMessage($recipientId, $storedPath, $mime);
            $mediaResponses[] = $response;

            Log::info('Facebook Media Response', $response);

            // Save attachment using the relationship
            $textMessage->attachments()->create([
                'type'                 => $facebookService->resolveMediaType($mime),
                'path'                 => $storedPath,
                'mime'                 => $mime,
                'size'                 => $file->getSize(),
            ]);
        }

        // Step 3: Send text after attachments
        if ($textMessage->content) {
            $textResponse = $facebookService->sendTextMessage($recipientId, $textMessage->content);
            $textMessage->update(['platform_message_id' => $textResponse['message_id'] ?? null]);
        }

        // Step 4: Return message with attachments loaded
        return jsonResponse('Messenger message(s) sent successfully.', true, new MessageResource($textMessage->load('attachments')));
    }
}
