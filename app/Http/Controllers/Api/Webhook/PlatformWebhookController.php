<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\WebsiteCustomerMessageRequest;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Platform;
use App\Models\User;
use App\Services\Platforms\EmailService;
use App\Services\Platforms\FacebookService;
use App\Services\Platforms\InstagramService;
use App\Services\Platforms\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webklex\IMAP\Facades\Client;

class PlatformWebhookController extends Controller
{
    protected $whatsAppService;

    protected $facebookService;

    protected $instagramService;

    protected $EmailService;

    public function __construct(WhatsAppService $whatsAppService, FacebookService $facebookService, InstagramService $instagramService, EmailService $EmailService)
    {
        $this->whatsAppService = $whatsAppService;
        $this->facebookService = $facebookService;
        $this->instagramService = $instagramService;
        $this->EmailService = $EmailService;
    }

    // Meta webhook callbackUrl verification endpoint
    public function verifyWhatsAppToken(Request $request)
    {
        $VERIFY_TOKEN = 'mahadi'; // must match Meta's setting

        $mode = $request->get('hub_mode');
        $token = $request->get('hub_verify_token');
        $challenge = $request->get('hub_challenge');

        if ($mode === 'subscribe' && $token === $VERIFY_TOKEN) {
            return response($challenge, 200);
        }

        return response('Verification token mismatch', 403);
    }

    public function incomingWhatsAppMessage(Request $request)
    {
        $data = $request->all();
        Log::info('WhatsApp Incoming Request', ['data' => $data]);

        $entry = $data['entry'][0]['changes'][0]['value'] ?? [];
        $statuses = $entry['statuses'] ?? [];
        $messages = $entry['messages'] ?? [];
        $contacts = $entry['contacts'][0] ?? null;

        $platform = Platform::whereRaw('LOWER(name) = ?', ['whatsapp'])->first();
        $platformId = $platform->id;
        $platformName = strtolower($platform->name);

        $phone = $contacts['wa_id'] ?? ($messages[0]['from'] ?? null);
        if (! $phone) {
            Log::warning('Invalid or missing phone number in request.');

            return response()->json(['status' => 'invalid_phone'], 400);
        }

        $senderName = $contacts['profile']['name'] ?? $phone;
        $payloadsToSend = [];

        DB::transaction(function () use ($statuses, $messages, $phone, $senderName, $platformId, $platformName, &$payloadsToSend) {

            // 1️⃣ Get or create customer
            $customer = Customer::firstOrCreate(
                ['phone' => $phone, 'platform_id' => $platformId],
                ['name' => $senderName]
            );

            // 2️⃣ Find or create active conversation
            $conversation = Conversation::where('customer_id', $customer->id)
                ->where('platform', $platformName)
                ->latest()
                ->first();

            $isNewConversation = false;

            if (! $conversation || $conversation->end_at !== null || $conversation->created_at < now()->subHours(config('services.conversation.conversation_expire_hours'))) {
                if ($conversation) {
                    updateUserInRedis($conversation->agent_id ? User::find($conversation->agent_id) : null, $conversation);
                }

                $conversation = Conversation::create([
                    'customer_id' => $customer->id,
                    'platform' => $platformName,
                    'trace_id' => 'WA-'.now()->format('YmdHis').'-'.uniqid(),
                    'agent_id' => null,
                ]);

                $isNewConversation = true;
            }

            // 3️⃣ Handle statuses
            foreach ($statuses as $status) {
                $payloadsToSend[] = [
                    'source' => 'whatsapp',
                    'traceId' => $conversation->trace_id,
                    'conversationId' => $conversation->id,
                    'conversationType' => $isNewConversation ? 'new' : 'old',
                    'sender' => $phone,
                    'api_key' => config('dispatcher.whatsapp_api_key'),
                    'timestamp' => $status['timestamp'] ?? time(),
                    'message' => $status['status'] ?? '',
                    'attachmentId' => [],
                    'attachments' => [],
                    'subject' => 'WhatsApp Status Update',
                ];
            }

            // 4️⃣ Handle incoming messages
            foreach ($messages as $msg) {
                $type = $msg['type'] ?? '';

                if (! in_array($type, ['text', 'image', 'video', 'document', 'audio'])) {
                    Log::info('Skipped unsupported WhatsApp message type', ['type' => $type, 'msg' => $msg]);

                    continue;
                }

                $caption = $msg['text']['body'] ?? null;
                $mediaIds = [];
                $attachments = [];
                $timestamp = $msg['timestamp'] ?? time();

                if ($type !== 'text') {
                    $mediaId = $msg[$type]['id'] ?? null;
                    if ($mediaId) {
                        $mediaIds[] = $mediaId;

                        $mediaData = $this->whatsAppService->getMediaUrlAndDownload($mediaId);
                        if ($mediaData) {
                            $attachments[] = [
                                'attachment_id' => $mediaId,
                                'path' => $mediaData['full_path'],
                                'is_download' => 1,
                                'mime' => $mediaData['mime'] ?? null,
                                'size' => $mediaData['size'] ?? null,
                                'type' => $mediaData['type'],
                            ];
                        } else {
                            Log::error("Media download failed for ID: {$mediaId}");
                        }
                    }

                    if (! $caption && ! empty($msg[$type]['caption'])) {
                        $caption = $msg[$type]['caption'];
                    }
                }

                // Handle replies
                $platformMessageId = $msg['id'] ?? null;
                $parentPlatformMessageId = $msg['context']['id'] ?? null;
                $parentId = null;

                if ($parentPlatformMessageId) {
                    $parent = Message::where('platform_message_id', $parentPlatformMessageId)->first();
                    $parentId = $parent?->id;
                }

                // Store message
                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $customer->id,
                    'sender_type' => Customer::class,
                    'type' => $type,
                    'content' => $caption,
                    'direction' => 'incoming',
                    'receiver_type' => User::class,
                    'receiver_id' => $conversation->agent_id ?? null,
                    'platform_message_id' => $platformMessageId,
                    'parent_id' => $parentId,
                ]);

                // Store attachments if present
                if (! empty($attachments)) {
                    $bulkInsert = array_map(fn ($att) => [
                        'message_id' => $message->id,
                        'attachment_id' => $att['attachment_id'],
                        'path' => $att['path'],
                        'type' => $att['type'],
                        'mime' => $att['mime'],
                        'size' => $att['size'],
                        'is_available' => $att['is_download'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], $attachments);

                    MessageAttachment::insert($bulkInsert);
                }

                $conversation->update(['last_message_id' => $message->id]);

                // Build payload
                $payloadsToSend[] = [
                    'source' => 'whatsapp',
                    'traceId' => $conversation->trace_id,
                    'conversationId' => $conversation->id,
                    'conversationType' => $isNewConversation ? 'new' : 'old',
                    'sender' => $phone,
                    'api_key' => config('dispatcher.whatsapp_api_key'),
                    'timestamp' => $timestamp,
                    'message' => $caption ?? null,
                    'attachmentId' => $mediaIds,
                    'attachments' => array_column($attachments, 'path'),
                    'subject' => "Customer Message from $senderName",
                    'messageId' => $message->id,
                    'parentMessageId' => $parentId,
                ];
            }

            // ✅ Dispatch all payloads after commit
            DB::afterCommit(function () use ($payloadsToSend) {
                foreach ($payloadsToSend as $payload) {
                    Log::info('✅ Dispatching WhatsApp Payload After Commit', ['payload' => $payload]);
                    $this->sendToDispatcher($payload);
                }
            });
        });

        return jsonResponse(! empty($messages) ? 'Message received' : 'Status received', true);
    }

    /**
     * Send payload to dispatcher API
     */
    private function sendToDispatcher(array $payload): void
    {
        try {
            $response = Http::acceptJson()->post(config('dispatcher.url').config('dispatcher.endpoints.handler'), $payload);

            if ($response->ok()) {
                Log::info('[CUSTOMER MESSAGE FORWARDED]', $payload);
            } else {
                Log::error('[CUSTOMER MESSAGE FORWARDED] FAILED', ['payload' => $payload, 'response' => $response->body()]);
            }
        } catch (\Exception $e) {
            Log::error('[CUSTOMER MESSAGE FORWARDED] ERROR', ['exception' => $e->getMessage()]);
        }
    }

    // Meta webhook callbackUrl verification endpoint
    public function verifyInstagram(Request $request)
    {
        $VERIFY_TOKEN = env('INSTAGRAM_VERIFY_TOKEN');

        $mode = $request->get('hub_mode');
        $token = $request->get('hub_verify_token');
        $challenge = $request->get('hub_challenge');

        Log::info('Instagram webhook verification attempt', [
            'mode' => $mode,
            'token' => $token,
            'challenge' => $challenge,
        ]);

        if ($mode === 'subscribe' && $token === $VERIFY_TOKEN) {
            return response($challenge, 200)
                ->header('Content-Type', 'text/plain');
        }

        return response('Verification token mismatch', 403);
    }

    /**
     * Receive webhook POST events from Instagram
     *     entry.messaging → for DMs (Direct Messages)
     *     entry.changes → for comments / mentions / feed events
     */
    public function receiveInstagramMessage(Request $request)
    {
        Log::info('📩 Instagram Webhook Payload:', $request->all());

        $entries = $request->input('entry', []);
        $platform = Platform::whereRaw('LOWER(name) = ?', ['instagram_message'])->first();
        $platformId = $platform->id ?? null;
        $platformName = strtolower($platform->name ?? 'instagram_message');

        foreach ($entries as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                $senderId = $event['sender']['id'] ?? null;
                $instagramId = $event['recipient']['id'] ?? null;
                $message = $event['message'] ?? [];
                $text = $message['text'] ?? null;
                $attachments = $message['attachments'] ?? [];
                $platformMessageId = $message['mid'] ?? null;
                // $isEcho = $message['is_echo'] ?? false;
                $timestamp = $event['timestamp'] ?? now()->timestamp;
                $parentMessageId = $message['reply_to']['mid'] ?? null;

                // ✅ Skip echoes or outgoing messages
                // if ($isEcho || $senderId === $instagramId) {
                //     Log::info('🌀 Skipping echo or outgoing message.', compact('senderId', 'instagramId'));

                //     continue;
                // }

                // ✅ Ensure valid IDs
                if (! $senderId || ! $platformMessageId) {
                    // Log::warning('⚠️ Missing senderId or message ID.', compact('event'));

                    continue;
                }

                Log::info('📥 Received new Instagram message', [
                    'sender' => $senderId,
                    'messageText' => $text,
                    'attachments' => $attachments,
                ]);

                /**
                 * STEP 1️⃣: Try to find existing customer in DB
                 */
                $existingCustomer = Customer::where('platform_user_id', $senderId)->first();

                if ($existingCustomer) {
                    Log::info('👤 Existing Instagram customer found in DB', [
                        'sender_id' => $senderId,
                        'customer_id' => $existingCustomer->id,
                        'name' => $existingCustomer->name,
                        'username' => $existingCustomer->username,
                    ]);

                    $username = $existingCustomer->username ?? 'unknown';
                    $senderName = $existingCustomer->name ?? "IG-{$username}-{$senderId}";
                    $profilePic = $existingCustomer->profile_photo ?? null;
                } else {
                    /**
                     * STEP 2️⃣: Fetch sender info from Graph API (if not cached)
                     */
                    $senderInfo = $this->instagramService->getIgUserInfo($senderId);

                    Log::info('👤 Instagram Sender Info (via API):', [
                        'sender_id' => $senderId,
                        'sender_info' => $senderInfo,
                    ]);

                    $username = $senderInfo['username'] ?? 'unknown';
                    $senderName = $senderInfo['name'] ?? "IG-{$username}-{$senderId}";
                    $profilePic = $senderInfo['profile_pic'] ?? null;
                }

                /**
                 * STEP 3️⃣: Save data inside a transaction
                 */
                DB::transaction(function () use (
                    $senderId,
                    $senderName,
                    $username,
                    $profilePic,
                    $platformId,
                    $platformName,
                    $text,
                    $attachments,
                    $timestamp,
                    $platformMessageId,
                    $parentMessageId
                ) {
                    // 1️⃣ Find or create the customer
                    $customer = Customer::firstOrCreate(
                        ['platform_user_id' => $senderId],
                        [
                            'name' => $senderName,
                            'username' => $username,
                            'platform_id' => $platformId,
                        ]
                    );

                    // 1️⃣a Download profile photo if new
                    if ($customer->wasRecentlyCreated && $profilePic) {
                        try {
                            $response = Http::get($profilePic);
                            if ($response->ok()) {
                                $extension = pathinfo(parse_url($profilePic, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                                $filename = "profile_photos/ig_{$customer->id}.".$extension;
                                Storage::disk('public')->put($filename, $response->body());
                                $customer->update(['profile_photo' => $filename]);
                            }
                        } catch (\Exception $e) {
                            Log::error('⚠️ Failed to download Instagram profile photo: '.$e->getMessage());
                        }
                    }

                    // 2️⃣ Find or create active conversation
                    $conversation = Conversation::where('customer_id', $customer->id)
                        ->where('platform', $platformName)
                        ->where(function ($query) {
                            $query->whereNull('end_at')
                                ->orWhere('created_at', '>=', now()->subHours(config('services.conversation.conversation_expire_hours')));
                        })
                        ->latest()
                        ->first();

                    $isNewConversation = false;
                    if (
                        ! $conversation ||
                        $conversation->end_at ||
                        $conversation->created_at < now()->subHours(config('services.conversation.conversation_expire_hours'))
                    ) {
                        $conversation = Conversation::create([
                            'customer_id' => $customer->id,
                            'platform' => $platformName,
                            'trace_id' => 'IGM-'.now()->format('YmdHis').'-'.uniqid(),
                        ]);
                        $isNewConversation = true;
                    }

                    // 3️⃣ Handle media attachments and determine normalized message type
                    $storedAttachments = [];
                    $mediaPaths = [];
                    $type = 'text'; // default

                    if (! empty($attachments)) {
                        $firstAttachment = $attachments[0];
                        $rawType = strtolower($firstAttachment['type'] ?? 'media');

                        // Normalize type
                        $type = match ($rawType) {
                            'image' => 'image',
                            'video' => 'video',
                            'audio' => 'audio',
                            'file', 'document' => 'document',
                            default => 'media',
                        };

                        foreach ($attachments as $attachment) {
                            $downloaded = $this->instagramService->downloadAttachment($attachment);
                            if ($downloaded) {
                                $storedAttachments[] = $downloaded;
                                $mediaPaths[] = $downloaded['path'];
                            }
                        }
                    } else {
                        $type = 'text';
                    }

                    Log::info('📎 Normalized Instagram attachment type', [
                        'rawType' => $rawType ?? null,
                        'normalizedType' => $type,
                        'attachments' => $attachments,
                    ]);

                    // 🧩 4️⃣ Safely resolve parent message (if exists)
                    $resolvedParentId = null;
                    if (! empty($parentMessageId)) {
                        $parentMessage = Message::where('platform_message_id', $parentMessageId)->first();
                        if ($parentMessage) {
                            // Threaded reply to a known message
                            $resolvedParentId = $parentMessage->id;
                        } else {
                            // Keep the platform message ID for reference (string column)
                            $resolvedParentId = $parentMessageId;
                        }
                    }

                    // 5️⃣ Safely create or skip duplicate messages
                    try {
                        $message = Message::firstOrCreate(
                            ['platform_message_id' => $platformMessageId],
                            [
                                'conversation_id' => $conversation->id,
                                'sender_id' => $customer->id,
                                'sender_type' => Customer::class,
                                // 'type' => ! empty($attachments) ? 'media' : 'text',
                                'type' => $type,
                                'content' => $text,
                                'direction' => 'incoming',
                                'receiver_type' => User::class,
                                'receiver_id' => $conversation->agent_id ?? null,
                                'parent_id' => $resolvedParentId, // ✅ FIXED
                            ]
                        );
                    } catch (\Illuminate\Database\QueryException $e) {
                        if ($e->errorInfo[1] == 1062) {
                            Log::info('⚠️ Duplicate message ignored safely', [
                                'platform_message_id' => $platformMessageId,
                            ]);

                            return;
                        }
                        throw $e;
                    }

                    // 6️⃣ Attach media files
                    if (! empty($storedAttachments)) {
                        $bulkInsert = array_map(function ($att) use ($message) {
                            return [
                                'message_id' => $message->id,
                                'attachment_id' => $att['attachment_id'],
                                'path' => $att['path'],
                                'type' => $att['type'],
                                'mime' => $att['mime'],
                                'is_available' => $att['is_download'],
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }, $storedAttachments);

                        MessageAttachment::insert($bulkInsert);
                    }

                    // 7️⃣ Update conversation with last message
                    $conversation->update(['last_message_id' => $message->id]);

                    // 8️⃣ Prepare dispatcher payload
                    $payload = [
                        'source' => 'instagram_message',
                        'traceId' => $conversation->trace_id,
                        'conversationId' => $conversation->id,
                        'conversationType' => $isNewConversation ? 'new' : 'old',
                        'sender' => $senderId,
                        'api_key' => config('dispatcher.instagram_api_key'),
                        'timestamp' => $timestamp,
                        'message' => $text,
                        'attachments' => $mediaPaths,
                        'subject' => "Instagram message from $senderName",
                        'messageId' => $message->id,
                    ];

                    // ✅ Send payload only after DB commit
                    DB::afterCommit(function () use ($payload) {
                        Log::info('📤 Forwarding Instagram payload (after commit)', ['payload' => $payload]);
                        $this->sendToDispatcher($payload);
                    });
                });
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    protected function sendInstagramMessage($recipientId, $message)
    {
        $accessToken = env('INSTAGRAM_PAGE_TOKEN'); // Page access token
        $url = 'https://graph.facebook.com/v24.0/me/messages';

        $payload = [
            'messaging_product' => 'instagram',
            'recipient' => ['id' => $recipientId],
            'message' => ['text' => $message],
        ];

        $response = Http::withToken($accessToken)->post($url, $payload);

        Log::info('📤 Instagram Reply Response:', [
            'recipient' => $recipientId,
            'payload' => $payload,
            'response' => $response->json(),
        ]);
    }

    // 1. Verify Meta Webhook (GET)
    public function verifyMessengerToken(Request $request)
    {
        $verify_token = env('FB_VERIFY_TOKEN');

        $mode = $request->get('hub_mode');
        $token = $request->get('hub_verify_token');
        $challenge = $request->get('hub_challenge');

        if ($mode === 'subscribe' && $token === $verify_token) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    // 2. Receive Message (POST)

    public function incomingMessengerMessage(Request $request)
    {
        Log::info('📩 Messenger Webhook Payload:', ['data' => $request->all()]);

        $entries = $request->input('entry', []);
        $platform = Platform::whereRaw('LOWER(name) = ?', ['facebook_messenger'])->first();
        $platformId = $platform->id ?? null;
        $platformName = strtolower($platform->name ?? 'facebook_messenger');

        foreach ($entries as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                $senderId = $event['sender']['id'] ?? null;
                $pageId = $event['recipient']['id'] ?? null;
                $timestamp = $event['timestamp'] ?? now()->timestamp;
                $messageData = $event['message'] ?? [];

                Log::info('⚠️ Messenger event', ['event' => $event]);

                // Skip outgoing messages from the page itself
                if ($senderId === $pageId) {
                    Log::info('➡️ Skipped outgoing message from page.', ['event' => $event]);

                    continue;
                }

                if (! $senderId) {
                    Log::warning('⚠️ Invalid Messenger event: missing senderId', ['event' => $event]);

                    continue;
                }

                $text = $messageData['text']['body'] ?? $messageData['text'] ?? null;
                $attachments = $messageData['attachments'] ?? [];
                $platformMessageId = $messageData['mid'] ?? null;
                $replyToMessageId = $messageData['reply_to']['mid'] ?? null;
                $parentMessageId = null;

                // Link to parent message if it is a reply
                if ($replyToMessageId) {
                    $parentMessageId = Message::where('platform_message_id', $replyToMessageId)->value('id');
                    Log::info('🔗 Found parent message for reply', [
                        'platform_message_id' => $platformMessageId,
                        'parent_message_id' => $parentMessageId,
                    ]);
                }

                // Fetch sender info
                $senderInfo = $this->facebookService->getSenderInfo($senderId);
                $senderName = $senderInfo['name'] ?? "Facebook User {$senderId}";
                $profilePic = $senderInfo['profile_pic'] ?? null;

                DB::transaction(function () use (
                    $senderId,
                    $senderName,
                    $profilePic,
                    $platformId,
                    $platformName,
                    $text,
                    $attachments,
                    $timestamp,
                    $platformMessageId,
                    $parentMessageId
                ) {
                    // 1️⃣ Find or create customer
                    $customer = Customer::firstOrCreate(
                        ['platform_user_id' => $senderId],
                        ['name' => $senderName, 'platform_id' => $platformId]
                    );

                    // Download profile photo for new customers
                    if ($customer->wasRecentlyCreated && $profilePic) {
                        try {
                            $response = Http::get($profilePic);
                            if ($response->ok()) {
                                $extension = pathinfo(parse_url($profilePic, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                                $filename = "profile_photos/fb_{$customer->id}.".$extension;
                                Storage::disk('public')->put($filename, $response->body());
                                $customer->update(['profile_photo' => $filename]);
                                Log::info('📷 Profile photo downloaded', ['customer_id' => $customer->id, 'path' => $filename]);
                            }
                        } catch (\Exception $e) {
                            Log::error("⚠️ Failed to download Facebook profile photo: {$e->getMessage()}");
                        }
                    }

                    // 2️⃣ Find or create conversation
                    $conversation = Conversation::where('customer_id', $customer->id)
                        ->where('platform', $platformName)
                        ->latest()
                        ->first();

                    $isNewConversation = false;
                    if (! $conversation || $conversation->end_at || $conversation->created_at < now()->subHours(config('services.conversation.conversation_expire_hours'))) {
                        if ($conversation) {
                            updateUserInRedis($conversation->agent_id ? User::find($conversation->agent_id) : null, $conversation);
                        }

                        $conversation = Conversation::create([
                            'customer_id' => $customer->id,
                            'platform' => $platformName,
                            'trace_id' => 'FB-'.now()->format('YmdHis').'-'.uniqid(),
                        ]);
                        $isNewConversation = true;
                        Log::info('🆕 New conversation created', ['conversation_id' => $conversation->id]);
                    }

                    // 3️⃣ Handle attachments (including captions)
                    $storedAttachments = [];
                    $mediaPaths = [];
                    $finalText = $text;

                    foreach ($attachments as $attachment) {
                        $downloaded = $this->facebookService->downloadAttachment($attachment);
                        if ($downloaded) {
                            $storedAttachments[] = $downloaded;
                            $mediaPaths[] = $downloaded['path'];

                            // If attachment has caption and no text, use caption as message content
                            if (empty($finalText) && ! empty($attachment['caption'])) {
                                $finalText = $attachment['caption'];
                            }
                        }
                    }

                    // 4️⃣ Store message
                    try {
                        $message = Message::firstOrCreate(
                            ['platform_message_id' => $platformMessageId],
                            [
                                'conversation_id' => $conversation->id,
                                'sender_id' => $customer->id,
                                'sender_type' => Customer::class,
                                'type' => ! empty($attachments) ? 'media' : 'text',
                                'content' => $finalText,
                                'direction' => 'incoming',
                                'receiver_type' => User::class,
                                'receiver_id' => $conversation->agent_id ?? null,
                                'parent_id' => $parentMessageId,
                            ]
                        );
                        Log::info('💬 Message stored', ['message_id' => $message->id]);
                    } catch (\Illuminate\Database\QueryException $e) {
                        if ($e->errorInfo[1] == 1062) {
                            Log::info('⚠️ Duplicate message ignored', ['platform_message_id' => $platformMessageId]);

                            return;
                        }
                        throw $e;
                    }

                    // 5️⃣ Attach files to message
                    if (! empty($storedAttachments)) {
                        $bulkInsert = array_map(fn ($att) => [
                            'message_id' => $message->id,
                            'attachment_id' => $att['attachment_id'],
                            'path' => $att['path'],
                            'type' => $att['type'],
                            'mime' => $att['mime'],
                            'is_available' => $att['is_download'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ], $storedAttachments);

                        MessageAttachment::insert($bulkInsert);
                        Log::info('📎 Attachments saved', ['message_id' => $message->id, 'count' => count($storedAttachments)]);
                    }

                    // 6️⃣ Update conversation
                    $conversation->update(['last_message_id' => $message->id]);
                    Log::info('📝 Conversation updated', ['conversation_id' => $conversation->id]);

                    // 7️⃣ Prepare and dispatch payload
                    $payload = [
                        'source' => 'facebook_messenger',
                        'traceId' => $conversation->trace_id,
                        'conversationId' => $conversation->id,
                        'conversationType' => $isNewConversation ? 'new' : 'old',
                        'sender' => $senderId,
                        'api_key' => config('dispatcher.messenger_api_key'),
                        'timestamp' => $timestamp,
                        'message' => $finalText ?? null,
                        'attachments' => $mediaPaths,
                        'subject' => "Facebook Messenger message from $senderName",
                        'messageId' => $message->id,
                        'parentMessageId' => $parentMessageId,
                    ];

                    DB::afterCommit(function () use ($payload) {
                        Log::info('📤 Forwarding Facebook payload (after commit)', ['payload' => $payload]);
                        $this->sendToDispatcher($payload);
                    });
                });
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    // 1. Verify Meta Webhook (GET)
    public function verifyFacebookPageToken(Request $request)
    {
        info(['requestData' => $request->all()]);
        // $verify_token = env('FACEBOOK_VERIFY_TOKEN');
        $verify_token = config('services.facebook.verify_token');

        info(['verify_token' => $verify_token]);

        $mode = $request->get('hub_mode');
        $token = $request->get('hub_verify_token');
        $challenge = $request->get('hub_challenge');

        if ($mode === 'subscribe' && $token === $verify_token) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    // 2. Receive Message (POST)

    public function receiveFacebookPageEventData(Request $request)
    {
        $payload = $request->all();
        Log::info('📩 Facebook Webhook Payload:', $payload);

        // Loop through entries
        if (isset($payload['entry'])) {
            foreach ($payload['entry'] as $entry) {
                // Messenger messages
                if (isset($entry['messaging'])) {
                    foreach ($entry['messaging'] as $msg) {
                        Log::info('Messenger Event:', $msg);
                    }
                }

                // Page feed events (comments, reactions)
                if (isset($entry['changes'])) {
                    foreach ($entry['changes'] as $change) {
                        if ($change['field'] === 'feed') {
                            Log::info('Page Feed Event:', $change['value']);
                        }
                    }
                }
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    public function receiveWebsitePageData(WebsiteCustomerMessageRequest $request)
    {
        $data = $request->all();
        Log::info('🌐 Website Incoming Request', ['data' => $data]);

        $platform = Platform::whereRaw('LOWER(name) = ?', ['website'])->first();
        $platformName = strtolower($platform->name);
        $customer = Customer::find($request->auth_customer->id);

        $payloadsToSend = [];

        DB::transaction(function () use ($customer, $platformName, &$payloadsToSend, $request) {
            // 🗨️ Get or create conversation
            $conversation = Conversation::where('customer_id', $customer->id)
                ->where('platform', $platformName)
                ->latest()
                ->first();

            $isNewConversation = false;

            if (
                ! $conversation ||
                $conversation->end_at ||
                $conversation->created_at < now()->subHours(config('services.conversation.conversation_expire_hours'))
            ) {
                if ($conversation) {
                    updateUserInRedis(
                        $conversation->agent_id ? User::find($conversation->agent_id) : null,
                        $conversation
                    );
                }

                $conversation = Conversation::create([
                    'customer_id' => $customer->id,
                    'platform' => $platformName,
                    'trace_id' => 'WEB-'.now()->format('YmdHis').'-'.uniqid(),
                    'agent_id' => null,
                ]);

                $isNewConversation = true;
            }

            // 💬 Create message
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $customer->id,
                'sender_type' => Customer::class,
                'type' => ! empty($request->content) ? 'text' : null,
                'content' => $request->input('content'),
                'direction' => 'incoming',
                'receiver_type' => User::class,
                'receiver_id' => $conversation->agent_id ?? null,
            ]);

            $attachmentPaths = [];

            // 📎 Handle attachments
            if ($request->hasFile('attachments')) {
                $bulkInsert = [];

                foreach ($request->file('attachments') as $file) {
                    $info = storeAndDetectAttachment($file, 'public', 'uploads/website/attachments');

                    $bulkInsert[] = [
                        'message_id' => $message->id,
                        'path' => $info['path'], // ✅ relative path only
                        'type' => $info['type'],
                        'mime' => $info['mime'],
                        'size' => $info['size'],
                        'is_available' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $attachmentPaths[] = $info['path'];
                }

                MessageAttachment::insert($bulkInsert);
            }

            // 🆙 Update conversation last message
            $conversation->update(['last_message_id' => $message->id]);

            // 🔑 Extend customer token
            $customer->update([
                'token_expires_at' => now()->addMinutes((int) config('services.conversation.website.token_expire_minutes')),
            ]);

            // 📦 Build payload
            $payload = [
                'source' => 'website',
                'traceId' => $conversation->trace_id,
                'conversationId' => $conversation->id,
                'conversationType' => $isNewConversation ? 'new' : 'old',
                'sender' => $customer->email ?? $customer->phone,
                'api_key' => config('dispatcher.website_api_key'),
                'timestamp' => time(),
                'message' => $message->content,
                'subject' => 'Customer Message from Website',
                'messageId' => $message->id,
            ];

            if (! empty($attachmentPaths)) {
                $payload['attachments'] = $attachmentPaths;
            }

            $payloadsToSend[] = $payload;

            // 🚀 Dispatch payloads after DB commit
            DB::afterCommit(function () use ($payloadsToSend) {
                foreach ($payloadsToSend as $payload) {
                    Log::info('✅ Dispatching Website Payload After Commit', ['payload' => $payload]);
                    $this->sendToDispatcher($payload);
                }
            });
        });

        return jsonResponse('Message received successfully', true);
    }

    public function receiveEmailData1(Request $request)
    {
        $receiveMail = $this->EmailService->receiveEmail();

        Log::info('Receive Email:', [
            'data' => $receiveMail,
        ]);

        return response()->json(['status' => 'processed']);
    }

    public function receiveEmailData(Request $request)
    {
        $platform = Platform::whereRaw('LOWER(name) = ?', ['email'])->first();

        if (! $platform) {
            Log::error('❌ Email platform not found in DB');

            return false;
        }

        $platformId = $platform->id;
        $platformName = strtolower($platform->name);

        $client = Client::account('gmail');

        try {
            $client->connect();
            Log::info('✅ Gmail IMAP connected successfully!');
        } catch (\Throwable $e) {
            Log::error('❌ Gmail IMAP connection failed: '.$e->getMessage());

            return false;
        }

        try {
            $inbox = $client->getFolder('INBOX');

            // $messages = $inbox->messages()
            //     ->unseen()           // fetch only unread messages
            //     ->limit(5)           // adjust limit as needed
            //     ->leaveUnread()      // do not mark as seen yet
            //     ->fetchOrderDesc()
            //     ->get();

            $messages = $inbox->messages()
                ->seen()
                ->limit(2)
                ->leaveUnread()
                ->fetchOrderDesc()
                ->get();

            foreach ($messages as $imapMsg) {
                try {
                    $this->processImapMessage($imapMsg, $platformId, $platformName);
                } catch (\Throwable $e) {
                    Log::error('⚠️ Error processing email: '.$e->getMessage(), [
                        'subject' => (string) $imapMsg->getSubject(),
                        'from' => (string) optional($imapMsg->getFrom()->first())->mail,
                    ]);
                }
            }

        } catch (\Throwable $e) {
            Log::error('IMAP read error: '.$e->getMessage());
        }

        $client->disconnect();

        // return true;
        return jsonResponse('Message received successfully', true);
    }

    /**
     * Process single IMAP message
     */
    private function processImapMessage($imapMsg, $platformId, $platformName)
    {
        $messageId = $imapMsg->getMessageId()->toString();

        // Skip duplicate messages
        if (Message::where('platform_message_id', $messageId)->exists()) {
            return;
        }

        $from = $imapMsg->getFrom()->first();
        $fromMail = (string) ($from->mail ?? '');
        $fromName = (string) ($from->personal ?? '');
        $toMails = implode(',', array_map(fn ($t) => (string) $t->mail, $imapMsg->getTo()->all()));
        $ccMails = implode(',', array_map(fn ($t) => (string) $t->mail, $imapMsg->getCc()->all()));
        $subject = (string) $imapMsg->getSubject();
        $htmlBody = (string) $imapMsg->getHTMLBody();
        // $timestamp = now()->timestamp;

        DB::transaction(function () use (
            $platformId, $platformName, $fromMail, $fromName, $ccMails, $subject, $htmlBody, $messageId, $imapMsg, &$conversation, &$attachmentsArr
        ) {
            // Customer
            $customer = Customer::firstOrCreate(
                ['email' => $fromMail, 'platform_id' => $platformId],
                ['name' => $fromName]
            );

            // Conversation
            $conversation = Conversation::firstOrCreate(
                ['customer_id' => $customer->id, 'platform' => $platformName],
                ['trace_id' => 'mail-'.now()->format('YmdHis').'-'.uniqid()]
            );

            // Message
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'platform_id' => $platformId,
                'sender_id' => $customer->id,
                'sender_type' => Customer::class,
                'cc_email' => $ccMails,
                'type' => 'text',
                'platform_message_id' => $messageId,
                'subject' => $subject,
                'content' => $htmlBody,
                'direction' => 'incoming',
            ]);

            // Attachments
            $attachmentsArr = $this->saveAttachments($imapMsg, $message);

            // Log info
            Log::info('📥 New email processed', [
                'subject' => $subject,
                'from' => $fromMail,
                'attachments_count' => count($attachmentsArr),
            ]);

            // Dispatch payload after commit
            $payload = [
                'source' => 'email',
                'traceId' => $conversation->trace_id,
                'conversationId' => $conversation->id,
                'conversationType' => 'new',
                'api_key' => config('dispatcher.email_api_key'),
                // 'timestamp' => $timestamp,
                'timestamp' => now()->timestamp,
                'senderName' => $fromName,
                'sender' => $fromMail,
                'cc' => $ccMails,
                'subject' => $subject,
                'html_body' => $htmlBody,
                'attachments' => $attachmentsArr,
                'messageId' => $message->id,
            ];

            Log::info('📤 Dispatching email payload', ['payload' => $payload]);

            DB::afterCommit(function () use ($payload) {
                $this->sendToDispatcher($payload);
            });

            // Mark email as seen
            $imapMsg->setFlag('Seen');
        });
    }

    /**
     * Save email attachments and return array for payload
     */
    private function saveAttachments($imapMsg, Message $message)
    {
        $attachmentsArr = [];

        foreach ($imapMsg->getAttachments() as $att) {

            $originalName = $att->name ?? 'file';
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            // Generate safe unique filename
            // $filename = Str::uuid().'_'.$att->name.'.'.$extension;
            $filename = Str::uuid().'.'.$extension;

            // Storage folder: storage/app/public/mail_attachments/YYYYMMDD/
            $storagePath = 'mail_attachments/'.now()->format('Ymd');
            $fullPath = $storagePath.'/'.$filename;

            // put file using Laravel Storage
            Storage::disk('public')->put($fullPath, $att->content);

            // Correct MIME detection
            $mime = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                default => 'application/octet-stream',
            };

            $attachmentsArr[] = [
                'message_id' => $message->id,
                'type' => $mime,
                'path' => $fullPath,
                'mime' => $mime,
                'size' => strlen($att->content),
                'attachment_id' => $att->id ?? null,
                'is_available' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($attachmentsArr)) {
            DB::transaction(function () use ($attachmentsArr) {
                MessageAttachment::insert($attachmentsArr);
            });
        }

        return $attachmentsArr;
    }

    public function download(MessageAttachment $attachment)
    {
        $relativePath = $attachment->path;
        $absolutePath = storage_path('app/public/'.$relativePath);

        if (! file_exists($absolutePath)) {
            return abort(404, 'Attachment file not found.');
        }

        // Use original file name if you want to give user-friendly name
        $filename = basename($absolutePath);

        // Correct MIME type
        $mime = $attachment->mime ?? 'application/octet-stream';

        return response()->download($absolutePath, $filename, [
            'Content-Type' => $mime,
        ]);
    }
}
