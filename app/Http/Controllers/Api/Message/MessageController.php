<?php

namespace App\Http\Controllers\Api\Message;

use App\Events\AgentAssignedToConversationEvent;
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
use App\Models\MessageAttachment;
use App\Models\User;
use App\Services\Platforms\EmailService;
use App\Services\Platforms\FacebookService;
use App\Services\Platforms\InstagramService;
use App\Services\Platforms\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    public function agentConversationList(Request $request)
    {
        $agentId = auth()->id(); // authenticated agent
        $data = $request->all();

        $pagination = ! isset($data['pagination']) || $data['pagination'] === 'true';
        $page = $data['page'] ?? 1;
        $perPage = $data['per_page'] ?? 10;
        $query = Conversation::with(['customer', 'agent', 'lastMessage'])->where('agent_id', $agentId)->latest();
        $isEnded = isset($data['is_ended']) && $data['is_ended'] === 'true' ? true : false;

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
        $data = $request->all();
        $pagination = ! isset($data['pagination']) || $data['pagination'] === 'true';
        $page = $data['page'] ?? 1;
        $perPage = $data['per_page'] ?? 10;
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
                    'messages' => MessageResource::collection($messages),
                    'pagination' => [
                        'current_page' => $messages->currentPage(),
                        'per_page' => $messages->perPage(),
                        'total' => $messages->total(),
                        'last_page' => $messages->lastPage(),
                    ],
                ]
            );
        }

        $allMessages = $query->get()->reverse()->values();

        return jsonResponse('Conversation messages retrieved successfully', true, ['conversation' => new ConversationResource($conversation), 'messages' => MessageResource::collection($allMessages)]);
    }

    public function incomingMsg2(Request $request)
    {
        $data = $request->all();
        Log::info('[IncomingMsg] Data', $data);
        $agentId = isset($data['agentId']) ? (int) $data['agentId'] : null;
        $agentAvailableScope = $data['availableScope'] ?? null;
        $source = strtolower($data['source'] ?? '');
        $conversationId = $data['messageData']['conversationId'] ?? null;
        $messageId = $data['messageData']['messageId'];
        $conversationType = $data['messageData']['conversationType'];

        // Validate required fields
        if (! $conversationId || ! $agentId) {
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
        $conversation = Conversation::find((int) $conversationId);
        Log::info('[IncomingMsg] before conversation', ['conversation' => $conversation,  'agentId' => $agentId]);

        if ($conversationType === 'new' || empty($conversation->agent_id)) {
            $conversation->agent_id = $user->id;
            $conversation->message_delivery_at = now();
            $conversation->agent_assigned_at   = now();
            $conversation->save();
        }

        Log::info('[IncomingMsg] after conversation', ['conversation' => $conversation,  'agentId' => $agentId]);

        $convertedMsgId = (int) $messageId;
        $message = Message::find($convertedMsgId);
        $message->update(['delivered_at' => now()]);

        if ($conversationType === 'new' || empty($message->receiver_id)) {
            $message->receiver_id = $conversation->agent_id ?? $user->id;
            $message->receiver_type = User::class;
            $message->save();
        }

        Log::info('[Message Data] Updated message', ['message' => $message, 'agentId' => $agentId]);

        // DB::commit();

        // Broadcast payload

        $payload = [
            'conversation' => new ConversationInfoResource($conversation, $message),
            'message'      => $message ? new MessageResource($message) : null,
        ];

        $channelData = [
            'platform' => $source,
            'agentId' => $agentId,
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

    public function incomingMsg(Request $request)
    {
        $data = $request->all();
        Log::info('[IncomingMsg2] Data', $data);

        $agentId          = $data['agentId'] ?? null;
        $availableScope   = $data['availableScope'] ?? null;
        $source           = strtolower($data['source'] ?? '');
        $conversationId   = $data['messageData']['conversationId'] ?? null;
        $messageId        = $data['messageData']['messageId'] ?? null;
        $conversationType = $data['messageData']['conversationType'] ?? null;

        // Basic validation
        if (! $conversationId || ! $agentId || ! $messageId) {
            return jsonResponse('Missing required fields: conversationId, agentId or messageId.', false, null, 400);
        }

        DB::transaction(function () use ($agentId, $availableScope, $conversationId, $messageId, $conversationType, $source) {

            // Update Agent Availability
            $user = User::findOrFail($agentId);
            $user->current_limit = $availableScope;
            $user->save();

            // Load Conversation & Message
            $conversation      = Conversation::findOrFail($conversationId);
            $message           = Message::findOrFail((int) $messageId);
            $isFirstAssignment = false;


            //Assign Agent Only First Time
            if ($conversationType === 'new' && empty($conversation->agent_id)) {

                $conversation->agent_id = $user->id;

                if (!$conversation->first_message_at) {
                    $conversation->first_message_at = now();
                }

                if (!$conversation->agent_assigned_at) {
                    $conversation->agent_assigned_at = now();
                }

                $conversation->save();

                $isFirstAssignment = true;
            }

            // Assign Message Receiver
            if ($conversationType === 'new' || empty($message->receiver_id)) {
                $message->receiver_id   = $conversation->agent_id ?? $user->id;
                $message->receiver_type = User::class;
            }

            $message->delivered_at = now();
            $message->save();

            //Update Conversation Timestamps
            $conversation->last_message_at = now();
            $conversation->last_message_id = $message->id;
            $conversation->save();

            // After Commit → Fire Event + Broadcast
            DB::afterCommit(function () use ($isFirstAssignment, $source, $conversation, $message, $user) {

                // Fire event ONLY 1 time
                if ($isFirstAssignment && $source === 'whatsapp') {
                    event(new AgentAssignedToConversationEvent($conversation, $user, $message->id));
                }

                // broadcasting payload
                $payload = [
                    'conversation' => new ConversationInfoResource($conversation, $message),
                    'message'      => new MessageResource($message),
                ];

                $channelData = [
                    'platform' => $source,
                    'agentId'  => $user->id,
                ];

                broadcast(new SocketIncomingMessage($payload, $channelData));
            });
        });

        return jsonResponse('Message received successfully.', true, null);
    }
    /**
     * End a conversation
     * - Updates conversation table
     * - Updates agent current_limit based on platform weight
     * - Updates Redis hash and list
     * - Conditionally removes platform from CONTACT_TYPE
     */
    public function endConversation(EndConversationRequest $request)
    {
        $user = auth()->user();
        $data = $request->validated();
        $conversation = Conversation::find($data['conversation_id']);

        if (! $conversation) {
            return jsonResponse('Conversation not found.', false, null, 404);
        }

        // Authorization check
        if ($user->id !== $conversation->agent_id) {
            return jsonResponse('You are not authorized to end this conversation.', false, null, 403);
        }

        // Check if already ended
        if ($conversation->end_at) {
            return jsonResponse('Conversation already ended.', false, null, 400);
        }

        // ✅ End the conversation
        $conversation->update([
            'end_at' => now(),
            'wrap_up_id' => $data['wrap_up_id'],
            'ended_by' => $user->id,
        ]);

        // ✅ Update agent current_limit based on platform weight
        $weight = getPlatformWeight($conversation->platform);
        $user->increment('current_limit', $weight);

        // ✅ Update Redis: hash + omnitrix list + conditional CONTACT_TYPE removal
        $this->updateUserInRedis($user, $conversation);

        return jsonResponse('Conversation ended successfully.', true);
    }

    /**
     * Update or add user data in Redis
     * - Updates agent hash ("agent:{id}")
     * - Removes the ended platform from CONTACT_TYPE if no other active conversations
     * - Pushes platform into "omnitrix_agent:{id}" list
     *
     * @param  \App\Models\User  $user
     * @param  string|null  $endedPlatform
     */
    private function updateUserInRedis($user, $conversation)
    {
        $endedPlatform = $conversation->platform;
        $hashKey = "agent:{$user->id}";
        $removedConversation = "conversation:{$conversation->id}";

        // Fetch existing CONTACT_TYPE from Redis hash
        $contactTypesJson = Redis::hGet($hashKey, 'CONTACT_TYPE') ?? '[]';
        $contactTypes = json_decode($contactTypesJson, true) ?: [];

        // Get all active conversations for this platform
        $activeConversations = Conversation::where('agent_id', $user->id)->where('platform', $endedPlatform)
            ->where(function ($query) {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', now()->subHours(config('services.conversation_expire_hours')));
            })
            ->count();

        // Remove platform only if no active conversations exist
        if ($endedPlatform && $activeConversations === 1 && in_array($endedPlatform, $contactTypes)) {
            $contactTypes = array_values(array_filter($contactTypes, fn($p) => $p !== $endedPlatform));
        }

        // Remove platform only if no active conversations exist
        if ($endedPlatform && $activeConversations === 0 && in_array($endedPlatform, $contactTypes)) {
            $contactTypes = array_values(array_filter($contactTypes, fn($p) => $p !== $endedPlatform));
        }

        // Prepare agent hash data
        $agentData = [
            'AGENT_ID' => $user->id,
            'AGENT_TYPE' => 'NORMAL',
            'STATUS' => $user->current_status,
            'MAX_SCOPE' => $user->max_limit,
            'AVAILABLE_SCOPE' => $user->current_limit,
            'CONTACT_TYPE' => json_encode($contactTypes),
            'SKILL' => json_encode($user->platforms()->pluck('name')->map(fn($n) => strtolower($n))->toArray()),
            'BUSYSINCE' => optional($user->changed_at)->format('Y-m-d H:i:s') ?? '',
        ];

        // Save hash in Redis
        Redis::hMSet($hashKey, $agentData);
        Redis::del($removedConversation); // Remove ended conversation key
    }

    public function sendWhatsAppMessageFromAgent1(SendPlatformMessageRequest $request)
    {
        $data = $request->validated();
        $conversation = Conversation::find($data['conversation_id']);
        $customer = Customer::find($conversation->customer_id);
        $phone = $customer->phone;

        // Save message in DB
        $message = new Message;
        $message->conversation_id = $conversation->id;
        $message->sender_id = auth()->id();
        $message->sender_type = User::class;
        $message->receiver_type = Customer::class;
        $message->receiver_id = $conversation->customer_id;
        $message->type = 'text';
        $message->content = $data['content'];
        $message->direction = 'outgoing';
        $message->save();

        $whatsAppService = new WhatsAppService;

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
                        'conversation_id' => $conversation->id,
                        'sender_id' => auth()->id(),
                        'sender_type' => User::class,
                        'receiver_type' => Customer::class,
                        'receiver_id' => $conversation->customer_id,
                        'type' => $mediaType,
                        'content' => null,
                        'direction' => 'outgoing',
                        'platform_message_id' => $mediaResponse['messages'][0]['id'] ?? null,
                        'parent_id' => $data['parent_id'] ?? null,
                    ]);
                }
            }
        }

        // If text provided, send last (after media)
        $textResponse = null;
        if (! empty($data['content'])) {
            $textResponse = $whatsAppService->sendTextMessage($phone, $data['content']);
            $message->update(['platform_message_id' => $textResponse['messages'][0]['id']]);
        }

        return jsonResponse('WhatsApp message(s) sent successfully.', true, [
            'text_message' => $message ? new MessageResource($message) : null,
            'media_responses' => $mediaResponses,
            'text_response' => $textResponse,
        ]);
    }

    public function sendMessagerMessageFromAgent1(SendPlatformMessageRequest $request)
    {
        $data = $request->validated();
        info(['$data' => $data]);
        $conversation = Conversation::find($data['conversation_id']);
        $customer = Customer::find($conversation->customer_id);
        $recipientId = $customer->platform_user_id; // make sure this field exists!

        $facebookService = new FacebookService;
        $mediaResponses = [];

        // Save text message in DB first (for tracking platform_message_id later)

        $textMessage = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => auth()->id(),
            'sender_type' => User::class,
            'receiver_type' => Customer::class,
            'receiver_id' => $customer->id,
            'type' => 'text',
            'content' => $data['content'],
            'direction' => 'outgoing',
            'platform' => 'messenger',
        ]);

        // Handle file uploads (attachments)
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $storedPath = $file->store('messenger_temp', 'public');
                $fullPath = 'messenger_temp/' . basename($storedPath);
                $mime = $file->getMimeType();

                // Send via Facebook API
                $response = $facebookService->sendAttachmentMessage($recipientId, $fullPath, $mime);
                $mediaResponses[] = $response;

                // Save media message to DB
                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => auth()->id(),
                    'sender_type' => User::class,
                    'receiver_type' => Customer::class,
                    'receiver_id' => $customer->id,
                    'type' => $facebookService->resolveMediaType($mime),
                    'content' => null,
                    'direction' => 'outgoing',
                    'platform' => 'messenger',
                    'platform_message_id' => $response['message_id'] ?? null,
                    'parent_id' => $data['parent_id'] ?? null,
                ]);
            }
        }

        // Send the text message *after* media
        $textResponse = null;
        if ($textMessage) {
            $textResponse = $facebookService->sendTextMessage($recipientId, $textMessage->content);
            $textMessage->update([
                'platform_message_id' => $textResponse['message_id'] ?? null,
            ]);
        }

        return jsonResponse('Messenger message(s) sent successfully.', true, [
            'text_message' => $textMessage ? new MessageResource($textMessage) : null,
            'media_responses' => $mediaResponses,
            'text_response' => $textResponse,
        ]);
    }

    /**
     * Send message from agent to customer across platforms (WhatsApp, Messenger).
     * Handles conversation creation/expiration and message with attachments.
     *
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
        $platformName = $customer->platform->name;

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
            'requestData' => $data,
            'attachmentsCount' => count($attachments),
            'attachements' => $attachments,
        ]);

        // Step 5: Send based on platform
        if ($platformName === 'facebook_messenger') {
            return $this->sendMessengerMessageFromAgent($data, $attachments, $conversation, $customer);
        } elseif ($platformName === 'whatsapp') {
            return $this->sendWhatsAppMessageFromAgent($data, $attachments, $conversation, $customer);
        } elseif ($platformName === 'website') {
            return $this->sendWebsiteMessageFromAgent($data, $attachments, $conversation, $customer);
        } elseif ($platformName === 'instagram_message') {
            return $this->sendInstagramMessageFromAgent($data, $attachments, $conversation, $customer);
        } elseif ($platformName === 'email') {
            return $this->sendEmailMessageFromAgent($data, $attachments, $conversation, $customer);
        }

        return jsonResponse('Unsupported platform', false, [], 422);
    }

    protected function getOrCreateConversationForAgentCustomer(int $customerId, int $agentId, string $platformName): Conversation
    {
        $expireHours = config('services.conversation_expire_hours');
        $now = now();

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
        if (! $conversation || $conversation->end_at !== null || $conversation->created_at < $now->subHours($expireHours)) {
            $conversation = Conversation::create([
                'customer_id' => $customerId,
                'agent_id' => $agentId,
                'platform' => $platformName,
                'trace_id' => strtoupper(substr($platformName, 0, 2)) . '-' . now()->format('YmdHis') . '-' . uniqid(),
            ]);
        }

        return $conversation;
    }

    protected function sendWhatsAppMessageFromAgent(array $data, array $attachments, Conversation $conversation, Customer $customer)
    {
        $phone = $customer->phone;

        // Step 1: Save the text message in DB
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => auth()->id(),
            'sender_type' => User::class,
            'receiver_type' => Customer::class,
            'receiver_id' => $customer->id,
            'type' => 'text',
            'content' => $data['content'] ?? '',
            'direction' => 'outgoing',
        ]);

        $whatsAppService = new WhatsAppService;
        $mediaResponses = [];

        // Step 2: Process attachments
        foreach ($attachments as $file) {
            // Save file in 'public' disk to generate URL
            $storedPath = $file->store('attachments', 'public');
            $fullPath = storage_path("app/public/{$storedPath}"); // local path for WhatsApp
            // $fileUrl    = asset("storage/{$storedPath}");           // URL to save in DB
            $mime = $file->getMimeType();
            $size = $file->getSize();

            // Determine attachment type
            $type = match (true) {
                str_starts_with($mime, 'image/') => 'image',
                str_starts_with($mime, 'video/') => 'video',
                str_starts_with($mime, 'audio/') => 'audio',
                default => 'document',
            };

            // Step 3: Save attachment info in DB
            $attachment = $message->attachments()->create([
                'type' => $type,
                'path' => $storedPath,  // save URL, not local path
                'mime' => $mime,
                'size' => $size,
                'is_available' => 1,
            ]);

            // Step 4: Upload file to WhatsApp
            $mediaId = $whatsAppService->uploadMedia($fullPath, $mime);
            if ($mediaId) {
                $mediaResponse = $whatsAppService->sendMediaMessage($phone, $mediaId, $type);
                $mediaResponses[] = $mediaResponse;

                // Update attachment record with WhatsApp media ID
                $attachment->update([
                    'attachment_id' => $mediaResponse['messages'][0]['id'] ?? null,
                ]);
            }
        }

        // Step 5: Send text message if any content exists
        $textResponse = null;
        if (! empty($data['content'])) {
            $textResponse = $whatsAppService->sendTextMessage($phone, $data['content']);
            $message->update([
                'platform_message_id' => $textResponse['messages'][0]['id'] ?? null,
            ]);
        }

        // Step 6: Return structured response
        return jsonResponse('WhatsApp message(s) sent successfully.', true, [
            'text_message' => $message ? new MessageResource($message) : null,
            'media_responses' => $mediaResponses,
            'text_response' => $textResponse,
        ]);
    }

    protected function sendMessengerMessageFromAgent(array $data, array $attachments, Conversation $conversation, Customer $customer)
    {
        $recipientId = $customer->platform_user_id;
        $facebookService = new FacebookService;
        $mediaResponses = [];

        // Step 1: Save main text message
        $textMessage = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => auth()->id(),
            'sender_type' => User::class,
            'receiver_type' => Customer::class,
            'receiver_id' => $customer->id,
            'type' => 'text',
            'content' => $data['content'] ?? '',
            'direction' => 'outgoing',
            'platform' => 'messenger',
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
                'type' => $facebookService->resolveMediaType($mime),
                'path' => $storedPath,
                'mime' => $mime,
                'size' => $file->getSize(),
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

    protected function sendWebsiteMessageFromAgent(array $data, array $attachments, Conversation $conversation, Customer $customer)
    {
        info('Sending website message from agent', [
            'data' => $data,
            'attachmentsCount' => count($attachments),
        ]);

        // Step 1: Save text message in DB
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => auth()->id(),
            'sender_type' => User::class,
            'receiver_type' => Customer::class,
            'receiver_id' => $customer->id,
            'type' => $data['content'] ? 'text' : null,
            'content' => $data['content'] ?? null,
            'direction' => 'outgoing',
        ]);

        $conversation->update(['last_message_id' => $message->id]);

        // Step 2: Handle attachments using helper
        if (! empty($attachments)) {
            $bulkInsert = [];

            foreach ($attachments as $file) {
                // Use helper to store file and detect type
                $info = storeAndDetectAttachment($file, 'public', 'uploads/website/attachments');

                $bulkInsert[] = [
                    'message_id' => $message->id,
                    'path' => $info['path'],
                    'type' => $info['type'],
                    'mime' => $info['mime'],
                    'size' => $info['size'],
                    'is_available' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            MessageAttachment::insert($bulkInsert);
        }

        // Step 3: Return message with attachments
        $message->refresh()->load('attachments');

        return jsonResponse('Website message sent successfully.', true, new MessageResource($message));
    }

    protected function sendInstagramMessageFromAgent(array $data, array $attachments, Conversation $conversation, Customer $customer)
    {
        $recipientId = $customer->platform_user_id;
        $instagramService = new InstagramService;
        $mediaResponses = [];

        // Step 1: Save main text message
        $textMessage = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => auth()->id(),
            'sender_type' => User::class,
            'receiver_type' => Customer::class,
            'receiver_id' => $customer->id,
            'type' => 'text',
            'content' => $data['content'] ?? '',
            'direction' => 'outgoing',
            'platform' => 'instagram_message',
        ]);

        // Step 2: Handle attachments
        // foreach ($attachments as $file) {
        //     $storedPath = $file->store('instagram_temp', 'public');
        //     $mime = $file->getMimeType();

        //     // Send to Instagram
        //     $response = $instagramService->sendAttachmentMessage($recipientId, $storedPath, $mime);
        //     info('Instagram Media Response', $response);
        //     $mediaResponses[] = $response;

        //     Log::info('Instagram Media Response', $response);

        //     // Save attachment using the relationship
        //     $textMessage->attachments()->create([
        //         'type' => $instagramService->resolveMediaType($mime),
        //         'path' => $storedPath,
        //         'mime' => $mime,
        //         'size' => $file->getSize(),
        //     ]);
        // }

        // Step 3: Send text after attachments
        if ($textMessage->content) {
            $textResponse = $instagramService->sendInstagramMessage($recipientId, $textMessage->content);
            $textMessage->update(['platform_message_id' => $textResponse['message_id']]);
            info('Instagram Text Response', $textResponse);
        }

        // Step 4: Return message with attachments loaded
        return jsonResponse('Instagram message(s) sent successfully.', true, new MessageResource($textMessage->load('attachments')));
    }

    protected function sendEmailMessageFromAgent(array $data, array $attachments, Conversation $conversation, Customer $customer)
    {
        info('Sending email message from agent', [
            'data' => $data,
            'attachmentsCount' => count($attachments),
        ]);

        /**
         * --------------------------------------------------
         * 1. CREATE MESSAGE (before sending)
         * --------------------------------------------------
         */
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => auth()->id(),
            'sender_type' => User::class,
            'receiver_type' => Customer::class,
            'receiver_id' => $customer->id,
            'type' => 'text',
            'content' => $data['content'] ?? '',
            'cc_email' => $data['cc_email'] ?? '',
            'subject' => $data['subject'] ?? '',
            'direction' => 'outgoing',
            'platform' => 'email',
        ]);

        $conversation->update(['last_message_id' => $message->id]);

        /**
         * --------------------------------------------------
         * 2. PROCESS ATTACHMENTS
         *  - Save files
         *  - Insert DB records
         *  - Build $savedPaths for EmailService
         * --------------------------------------------------
         */
        $savedPaths = [];
        $attachmentRows = [];
        $storagePath = 'mail_attachments/' . now()->format('Ymd');

        foreach ($attachments as $file) {

            if (! $file instanceof \Illuminate\Http\UploadedFile) {
                continue;
            }

            $ext = strtolower($file->getClientOriginalExtension());
            $filename = Str::uuid() . '.' . $ext;
            $relativePath = $storagePath . '/' . $filename;

            // Save file to storage/app/public
            Storage::disk('public')->put($relativePath, file_get_contents($file));

            // Generate MIME
            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                default => $file->getClientMimeType(),
            };

            $attachmentRows[] = [
                'message_id' => $message->id,
                'path' => $relativePath,
                'type' => $mime,
                'mime' => $mime,
                'size' => $file->getSize(),
                'is_available' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Add to sendMail()
            $savedPaths[] = $relativePath;
        }

        if (! empty($attachmentRows)) {
            MessageAttachment::insert($attachmentRows);
        }

        /**
         * --------------------------------------------------
         * 3. SEND EMAIL (after attachments saved)
         * --------------------------------------------------
         */
        $EmailService = new EmailService;
        // ---- 4. Prepare CC ----
        $ccEmail = ! empty($data['cc_email'])
            ? array_map('trim', explode(',', $data['cc_email']))
            : [];

        $emailResponse = $EmailService->sendEmail(
            $customer->email,
            $data['subject'],
            $data['content'],
            $savedPaths,  // attachments
            $ccEmail      // CC array
        );

        info('Email Response', $emailResponse);

        // Optional: Save message platform_message_id
        $message->update([
            'platform_message_id' => $emailResponse['sent_at'],
        ]);

        /**
         * --------------------------------------------------
         * 5. RETURN RESPONSE
         * --------------------------------------------------
         */
        return jsonResponse('Email message sent successfully.', true, new MessageResource($message->load('attachments')));
    }
}
