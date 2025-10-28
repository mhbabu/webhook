<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Events\SocketIncomingMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\WebsiteCustomerMessageRequest;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Platform;
use App\Models\User;
use App\Services\Platforms\FacebookService;
use App\Services\Platforms\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PlatformWebhookController extends Controller
{
    protected $whatsAppService;
    protected $facebookService;


    public function __construct(WhatsAppService $whatsAppService, FacebookService $facebookService)
    {
        $this->whatsAppService = $whatsAppService;
        $this->facebookService = $facebookService;
    }

    // Meta webhook callbackUrl verification endpoint
    public function verifyWhatsAppToken(Request $request)
    {
        $VERIFY_TOKEN = 'mahadi'; // must match Meta's setting

        $mode      = $request->get('hub_mode');
        $token     = $request->get('hub_verify_token');
        $challenge = $request->get('hub_challenge');

        if ($mode === 'subscribe' && $token === $VERIFY_TOKEN) {
            return response($challenge, 200);
        }

        return response('Verification token mismatch', 403);
    }

    public function incomingWhatsAppMessage1(Request $request)
    {
        // Capture and log the entire incoming request
        $data = $request->all();
        Log::info('WhatsApp Incoming Request', ['data' => $data]);

        $entry    = $data['entry'][0]['changes'][0]['value'] ?? [];
        $statuses = $entry['statuses'] ?? [];
        $messages = $entry['messages'] ?? [];
        $contacts = $entry['contacts'][0] ?? null;

        // Find WhatsApp platform
        $platform     = Platform::whereRaw('LOWER(name) = ?', ['whatsapp'])->first();
        $platformId   = $platform->id;
        $platformName = strtolower($platform->name);

        // Determine sender's phone and name
        $phone = $contacts['wa_id'] ?? ($messages[0]['from'] ?? null);
        if (!$phone) {
            Log::warning('Invalid or missing phone number in request.');
            return response()->json(['status' => 'invalid_phone'], 400);
        }

        $senderName = $contacts['profile']['name'] ?? $phone;

        // Collect all payloads to send after DB commit
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
                // ->where(function ($q) {
                //     $q->whereNull('end_at')
                //         ->orWhere('created_at', '>=', now()->subHours(config('services.conversation.conversation_expire_hours')));
                // })
                ->latest()
                ->first();

            $isNewConversation = false;

            if (!$conversation || $conversation->end_at !== null || $conversation->created_at < now()->subHours(config('services.conversation.conversation_expire_hours'))) {

                // Clean old conversation from Redis
                if ($conversation) {
                    updateUserInRedis($conversation->agent_id ? User::find($conversation->agent_id) : null, $conversation);
                }

                $conversation = new Conversation();
                $conversation->customer_id = $customer->id;
                $conversation->platform    = $platformName;
                $conversation->trace_id    = "WA-" . now()->format('YmdHis') . '-' . uniqid();
                $conversation->agent_id    = null;
                $conversation->save();

                $isNewConversation = true;
            }

            // 3️⃣ Handle statuses
            foreach ($statuses as $status) {
                $payloadsToSend[] = [
                    "source"           => "whatsapp",
                    "traceId"          => $conversation->trace_id,
                    "conversationId"   => $conversation->id,
                    "conversationType" => $isNewConversation ? "new" : "old",
                    "sender"           => $phone,
                    "api_key"          => config('dispatcher.whatsapp_api_key'),
                    "timestamp"        => $status['timestamp'] ?? time(),
                    "message"          => $status['status'] ?? '',
                    "attachmentId"     => [],
                    "attachments"      => [],
                    "subject"          => "WhatsApp Status Update",
                ];
            }

            // 4️⃣ Handle incoming messages
            foreach ($messages as $msg) {
                $type = $msg['type'] ?? '';

                // Skip unsupported types
                if (!in_array($type, ['text', 'image', 'video', 'document', 'audio'])) {
                    Log::info('Skipped unsupported WhatsApp message type', ['type' => $type, 'msg' => $msg]);
                    continue;
                }

                $caption     = null;
                $mediaIds    = [];
                $attachments = [];
                $timestamp   = $msg['timestamp'] ?? time();

                if ($type === 'text') {
                    $caption = $msg['text']['body'] ?? '';
                } else {
                    $mediaId = $msg[$type]['id'] ?? null;

                    if ($mediaId) {
                        $mediaIds[] = $mediaId;

                        $mediaData = $this->whatsAppService->getMediaUrlAndDownload($mediaId);
                        if ($mediaData) {
                            $attachments[] = [
                                'attachment_id' => $mediaId,
                                'path'          => $mediaData['full_path'],
                                'is_download'   => 1,
                                'mime'          => $mediaData['mime'] ?? null,
                                'size'          => $mediaData['size'] ?? null,
                                'type'          => $mediaData['type'],
                            ];
                        } else {
                            Log::error("Media download failed for ID: {$mediaId}");
                        }
                    }

                    if (!$caption && !empty($msg[$type]['caption'])) {
                        $caption = $msg[$type]['caption'];
                    }
                }

                // Handle reply context
                $platformMessageId        = $msg['id'] ?? null;
                $parentPlatformMessageId  = $msg['context']['id'] ?? null;
                $parentId                 = null;

                if ($parentPlatformMessageId) {
                    $parent   = Message::where('platform_message_id', $parentPlatformMessageId)->first();
                    $parentId = $parent?->id;
                }

                // Store message
                $message = Message::create([
                    'conversation_id'     => $conversation->id,
                    'sender_id'           => $customer->id,
                    'sender_type'         => Customer::class,
                    'type'                => $type,
                    'content'             => $caption,
                    'direction'           => 'incoming',
                    'receiver_type'       => User::class,
                    'receiver_id'         => $conversation->agent_id ?? null,
                    'platform_message_id' => $platformMessageId,
                    'parent_id'           => $parentId,
                ]);

                // Save attachments if available
                if (!empty($attachments)) {
                    $bulkInsert = array_map(fn($att) => [
                        'message_id'    => $message->id,
                        'attachment_id' => $att['attachment_id'],
                        'path'          => $att['path'],
                        'type'          => $att['type'],
                        'mime'          => $att['mime'],
                        'size'          => $att['size'],
                        'is_available'  => $att['is_download'],
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ], $attachments);

                    MessageAttachment::insert($bulkInsert);
                }

                // Update conversation
                $conversation->update(['last_message_id' => $message->id]);

                // Build payload
                $payloadsToSend[] = [
                    "source"           => "whatsapp",
                    "traceId"          => $conversation->trace_id,
                    "conversationId"   => $conversation->id,
                    "conversationType" => $isNewConversation ? "new" : "old",
                    "sender"           => $phone,
                    "api_key"          => config('dispatcher.whatsapp_api_key'),
                    "timestamp"        => $timestamp,
                    "message"          => $caption ?? null,
                    "attachmentId"     => $mediaIds,
                    "attachments"      => array_column($attachments, 'path'),
                    "subject"          => "Customer Message from $senderName",
                    "messageId"        => $message->id,
                ];
            }

            // ✅ Send all payloads only after transaction commit
            DB::afterCommit(function () use ($payloadsToSend) {
                foreach ($payloadsToSend as $payload) {
                    Log::info('✅ Dispatching WhatsApp Payload After Commit', ['payload' => $payload]);
                    $this->sendToDispatcher($payload);
                }
            });
        });

        return jsonResponse(!empty($messages) ? 'Message received' : 'Status received', true);
    }

    public function incomingWhatsAppMessage(Request $request)
    {
        $data = $request->all();
        Log::info('WhatsApp Incoming Request', ['data' => $data]);

        $entry    = $data['entry'][0]['changes'][0]['value'] ?? [];
        $statuses = $entry['statuses'] ?? [];
        $messages = $entry['messages'] ?? [];
        $contacts = $entry['contacts'][0] ?? null;

        $platform     = Platform::whereRaw('LOWER(name) = ?', ['whatsapp'])->first();
        $platformId   = $platform->id;
        $platformName = strtolower($platform->name);

        $phone = $contacts['wa_id'] ?? ($messages[0]['from'] ?? null);
        if (!$phone) {
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

            if (!$conversation || $conversation->end_at !== null || $conversation->created_at < now()->subHours(config('services.conversation.conversation_expire_hours'))) {
                if ($conversation) {
                    updateUserInRedis($conversation->agent_id ? User::find($conversation->agent_id) : null, $conversation);
                }

                $conversation = Conversation::create([
                    'customer_id' => $customer->id,
                    'platform'    => $platformName,
                    'trace_id'    => "WA-" . now()->format('YmdHis') . '-' . uniqid(),
                    'agent_id'    => null,
                ]);

                $isNewConversation = true;
            }

            // 3️⃣ Handle statuses
            foreach ($statuses as $status) {
                $payloadsToSend[] = [
                    "source"           => "whatsapp",
                    "traceId"          => $conversation->trace_id,
                    "conversationId"   => $conversation->id,
                    "conversationType" => $isNewConversation ? "new" : "old",
                    "sender"           => $phone,
                    "api_key"          => config('dispatcher.whatsapp_api_key'),
                    "timestamp"        => $status['timestamp'] ?? time(),
                    "message"          => $status['status'] ?? '',
                    "attachmentId"     => [],
                    "attachments"      => [],
                    "subject"          => "WhatsApp Status Update",
                ];
            }

            // 4️⃣ Handle incoming messages
            foreach ($messages as $msg) {
                $type = $msg['type'] ?? '';

                if (!in_array($type, ['text', 'image', 'video', 'document', 'audio'])) {
                    Log::info('Skipped unsupported WhatsApp message type', ['type' => $type, 'msg' => $msg]);
                    continue;
                }

                $caption     = $msg['text']['body'] ?? null;
                $mediaIds    = [];
                $attachments = [];
                $timestamp   = $msg['timestamp'] ?? time();

                if ($type !== 'text') {
                    $mediaId = $msg[$type]['id'] ?? null;
                    if ($mediaId) {
                        $mediaIds[] = $mediaId;

                        $mediaData = $this->whatsAppService->getMediaUrlAndDownload($mediaId);
                        if ($mediaData) {
                            $attachments[] = [
                                'attachment_id' => $mediaId,
                                'path'          => $mediaData['full_path'],
                                'is_download'   => 1,
                                'mime'          => $mediaData['mime'] ?? null,
                                'size'          => $mediaData['size'] ?? null,
                                'type'          => $mediaData['type'],
                            ];
                        } else {
                            Log::error("Media download failed for ID: {$mediaId}");
                        }
                    }

                    if (!$caption && !empty($msg[$type]['caption'])) {
                        $caption = $msg[$type]['caption'];
                    }
                }

                // Handle replies
                $platformMessageId       = $msg['id'] ?? null;
                $parentPlatformMessageId = $msg['context']['id'] ?? null;
                $parentId                = null;

                if ($parentPlatformMessageId) {
                    $parent   = Message::where('platform_message_id', $parentPlatformMessageId)->first();
                    $parentId = $parent?->id;
                }

                // Store message
                $message = Message::create([
                    'conversation_id'     => $conversation->id,
                    'sender_id'           => $customer->id,
                    'sender_type'         => Customer::class,
                    'type'                => $type,
                    'content'             => $caption,
                    'direction'           => 'incoming',
                    'receiver_type'       => User::class,
                    'receiver_id'         => $conversation->agent_id ?? null,
                    'platform_message_id' => $platformMessageId,
                    'parent_id'           => $parentId,
                ]);

                // Store attachments if present
                if (!empty($attachments)) {
                    $bulkInsert = array_map(fn($att) => [
                        'message_id'    => $message->id,
                        'attachment_id' => $att['attachment_id'],
                        'path'          => $att['path'],
                        'type'          => $att['type'],
                        'mime'          => $att['mime'],
                        'size'          => $att['size'],
                        'is_available'  => $att['is_download'],
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ], $attachments);

                    MessageAttachment::insert($bulkInsert);
                }

                $conversation->update(['last_message_id' => $message->id]);

                // Build payload
                $payloadsToSend[] = [
                    "source"           => "whatsapp",
                    "traceId"          => $conversation->trace_id,
                    "conversationId"   => $conversation->id,
                    "conversationType" => $isNewConversation ? "new" : "old",
                    "sender"           => $phone,
                    "api_key"          => config('dispatcher.whatsapp_api_key'),
                    "timestamp"        => $timestamp,
                    "message"          => $caption ?? null,
                    "attachmentId"     => $mediaIds,
                    "attachments"      => array_column($attachments, 'path'),
                    "subject"          => "Customer Message from $senderName",
                    "messageId"        => $message->id,
                    "parentMessageId"  => $parentId,
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

        return jsonResponse(!empty($messages) ? 'Message received' : 'Status received', true);
    }

    /**
     * Send payload to dispatcher API
     */
    private function sendToDispatcher(array $payload): void
    {
        try {
            $response = Http::acceptJson()->post(config('dispatcher.url') . config('dispatcher.endpoints.handler'), $payload);

            if ($response->ok()) {
                Log::info("[CUSTOMER MESSAGE FORWARDED]", $payload);
            } else {
                Log::error("[CUSTOMER MESSAGE FORWARDED] FAILED", ['payload' => $payload, 'response' => $response->body()]);
            }
        } catch (\Exception $e) {
            Log::error("[CUSTOMER MESSAGE FORWARDED] ERROR", ['exception' => $e->getMessage()]);
        }
    }

    // Meta Webhook verification (GET request)
    public function verifyIntragram(Request $request)
    {
        $verify_token = env('INSTAGRAM_VERIFY_TOKEN'); // Get from .env
        $mode         = $request->get('hub_mode');
        $token        = $request->get('hub_verify_token');
        $challenge    = $request->get('hub_challenge');

        if ($mode === 'subscribe' && $token === $verify_token) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    // Receiving webhook POST payload
    public function receiveInstagramMsg2(Request $request)
    {
        Log::info('📩 Instagram Webhook Data:', $request->all());

        // Dispatch job or process data
        return response('EVENT_RECEIVED', 200);
    }

    public function receiveInstragramMsg(Request $request)
    {
        Log::info('📩 Instagram Webhook Payload:', $request->all());

        $entries = $request->input('entry', []);
        foreach ($entries as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $senderId = $value['from'] ?? null;
                $messageText = $value['message'] ?? null;

                if ($senderId && $messageText) {
                    // Send reply
                    $this->sendInstagramMessage($senderId, "You said: " . $messageText);
                }
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    protected function sendInstagramMessage($recipientId, $message)
    {
        $accessToken = env('INSTAGRAM_GRAPH_TOKEN'); // Your page access token
        $url = "https://graph.facebook.com/v19.0/me/messages";

        $payload = [
            'messaging_product' => 'instagram',
            'recipient' => ['id' => $recipientId],
            'message' => ['text' => $message],
        ];

        $response = Http::withToken($accessToken)
            ->post($url, $payload);

        Log::info('📤 Instagram Reply Response:', [
            'recipient' => $recipientId,
            'payload' => $payload,
            'response' => $response->json()
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

    public function incomingMessengerMessage1(Request $request)
    {
        Log::info('📩 Messenger Webhook Payload:', ['data' => $request->all()]);

        $entries       = $request->input('entry', []);
        $platform      = Platform::whereRaw('LOWER(name) = ?', ['facebook_messenger'])->first();
        $platformId    = $platform->id ?? null;
        $platformName  = strtolower($platform->name ?? 'facebook_messenger');

        foreach ($entries as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                $senderId    = $event['sender']['id'] ?? null;
                $pageId      = $event['recipient']['id'] ?? null;
                $timestamp   = $event['timestamp'] ?? now()->timestamp;
                $messageData = $event['message'] ?? [];

                Log::info('⚠️ Messenger event', ['event' => $event]);

                // 🛑 Skip outgoing messages (from your facebook_messenger)
                if ($senderId === $pageId) {
                    Log::info('➡️ Skipped outgoing message from page.', ['event' => $event]);
                    continue;
                }

                if (!$senderId) {
                    Log::warning('⚠️ Invalid Messenger event: missing senderId', ['event' => $event]);
                    continue;
                }

                $text              = $messageData['text'] ?? null;
                $attachments       = $messageData['attachments'] ?? [];
                $platformMessageId = $messageData['mid'] ?? null;
                $replyToMessageId  = $messageData['reply_to']['mid'] ?? null;
                $parentMessageId   = null;

                // ✅ Find parent internal message if exists
                if ($replyToMessageId) {
                    $parentMessageId = Message::where('platform_message_id', $replyToMessageId)->value('id');
                }

                // ✅ Fetch sender details from Facebook API
                $senderInfo = $this->facebookService->getSenderInfo($senderId);
                $senderName = $senderInfo['name'] ?? "Facebook User {$senderId}";
                $profilePic = $senderInfo['profile_pic'] ?? null;

                DB::transaction(function () use ($senderId, $senderName, $profilePic, $platformId, $platformName, $text, $attachments, $timestamp, $platformMessageId, $parentMessageId) {
                    // 1️⃣ Find or create the customer
                    $customer = Customer::where('platform_user_id', $senderId)->first();
                    if (!$customer) {
                        $customer = Customer::create([
                            'platform_user_id' => $senderId,
                            'name'             => $senderName,
                            'platform_id'      => $platformId,
                        ]);
                    }

                    // 1️⃣a Download profile photo only when customer is newly created
                    if ($customer->wasRecentlyCreated && $profilePic) {
                        try {
                            $response = Http::get($profilePic);
                            if ($response->ok()) {
                                $extension = pathinfo(parse_url($profilePic, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                                $filename = "profile_photos/fb_{$customer->id}." . $extension;
                                Storage::disk('public')->put($filename, $response->body());
                                $customer->profile_photo = $filename;
                                $customer->save();
                            }
                        } catch (\Exception $e) {
                            Log::error("⚠️ Failed to download Facebook profile photo: " . $e->getMessage());
                        }
                    }

                    // 2️⃣ Find or create an active conversation
                    $conversation = Conversation::where('customer_id', $customer->id)
                        ->where('platform', $platformName)
                        // ->where(function ($query) {
                        //     $query->whereNull('end_at')
                        //         ->orWhere('created_at', '>=', now()->subHours(config('services.conversation.conversation_expire_hours')));
                        // })
                        ->latest()
                        ->first();

                    $isNewConversation = false;

                    if (!$conversation || $conversation->end_at || $conversation->created_at < now()->subHours(config('services.conversation.conversation_expire_hours'))) {

                        // Clean old conversation from Redis
                        if ($conversation) {
                            updateUserInRedis($conversation->agent_id ? User::find($conversation->agent_id) : null, $conversation);
                        }

                        $conversation = new Conversation();
                        $conversation->customer_id = $customer->id;
                        $conversation->platform = $platformName;
                        $conversation->trace_id = "FB-" . now()->format('YmdHis') . '-' . uniqid();
                        $conversation->save();
                        $isNewConversation = true;
                    }

                    // 3️⃣ Handle media attachments
                    $storedAttachments = [];
                    $mediaPaths = [];

                    foreach ($attachments as $attachment) {
                        $downloaded = $this->facebookService->downloadAttachment($attachment);
                        if ($downloaded) {
                            $storedAttachments[] = $downloaded;
                            $mediaPaths[] = $downloaded['path'];
                        }
                    }

                    // 4️⃣ Safely create or get existing message
                    try {
                        $message = Message::firstOrCreate(
                            ['platform_message_id' => $platformMessageId],
                            [
                                'conversation_id'     => $conversation->id,
                                'sender_id'           => $customer->id,
                                'sender_type'         => Customer::class,
                                'type'                => !empty($attachments) ? 'media' : 'text',
                                'content'             => $text,
                                'direction'           => 'incoming',
                                'receiver_type'       => User::class,
                                'receiver_id'         => $conversation->agent_id ?? null,
                                'parent_id'           => $parentMessageId,
                            ]
                        );
                    } catch (\Illuminate\Database\QueryException $e) {
                        if ($e->errorInfo[1] == 1062) {
                            Log::info('⚠️ Duplicate message ignored safely', [
                                'platform_message_id' => $platformMessageId
                            ]);
                            return; // Skip further processing
                        }
                        throw $e;
                    }

                    // 5️⃣ Attach files to message (if any)
                    if (!empty($storedAttachments)) {
                        $bulkInsert = array_map(function ($att) use ($message) {
                            return [
                                'message_id'    => $message->id,
                                'attachment_id' => $att['attachment_id'],
                                'path'          => $att['path'],
                                'type'          => $att['type'],
                                'mime'          => $att['mime'],
                                'is_available'  => $att['is_download'],
                                'created_at'    => now(),
                                'updated_at'    => now(),
                            ];
                        }, $storedAttachments);

                        MessageAttachment::insert($bulkInsert);
                    }

                    // 6️⃣ Update conversation with latest message
                    $conversation->update(['last_message_id' => $message->id]);

                    // 7️⃣ Prepare payload
                    $payload = [
                        "source"           => "facebook_messenger",
                        "traceId"          => $conversation->trace_id,
                        "conversationId"   => $conversation->id,
                        "conversationType" => $isNewConversation ? "new" : "old",
                        "sender"           => $senderId,
                        "api_key"          => config('dispatcher.messenger_api_key'),
                        "timestamp"        => $timestamp,
                        "message"          => $text ?? null,
                        "attachments"      => $mediaPaths,
                        "subject"          => "Facebook Messenger message from $senderName",
                        "messageId"        => $message->id,
                    ];

                    // ✅ Wait until DB commits successfully before sending payload
                    DB::afterCommit(function () use ($payload) {
                        Log::info('📤 Forwarding Facebook payload (after commit)', ['payload' => $payload]);
                        $this->sendToDispatcher($payload);
                    });
                });
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    public function incomingMessengerMessage(Request $request)
    {
        Log::info('📩 Messenger Webhook Payload:', ['data' => $request->all()]);

        $entries      = $request->input('entry', []);
        $platform     = Platform::whereRaw('LOWER(name) = ?', ['facebook_messenger'])->first();
        $platformId   = $platform->id ?? null;
        $platformName = strtolower($platform->name ?? 'facebook_messenger');

        foreach ($entries as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                $senderId    = $event['sender']['id'] ?? null;
                $pageId      = $event['recipient']['id'] ?? null;
                $timestamp   = $event['timestamp'] ?? now()->timestamp;
                $messageData = $event['message'] ?? [];

                // Skip outgoing messages
                if ($senderId === $pageId) continue;
                if (!$senderId) continue;

                $text              = $messageData['text'] ?? null;
                $attachments       = $messageData['attachments'] ?? [];
                $platformMessageId = $messageData['mid'] ?? null;
                $replyToMessageId  = $messageData['reply_to']['mid'] ?? null;
                $parentMessageId   = null;

                // Link to parent message if it's a reply
                if ($replyToMessageId) {
                    $parentMessageId = Message::where('platform_message_id', $replyToMessageId)->value('id');
                }

                // Fetch sender info from Facebook
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
                                $filename = "profile_photos/fb_{$customer->id}." . $extension;
                                Storage::disk('public')->put($filename, $response->body());
                                $customer->update(['profile_photo' => $filename]);
                            }
                        } catch (\Exception $e) {
                            Log::error("Failed to download Facebook profile photo: {$e->getMessage()}");
                        }
                    }

                    // 2️⃣ Find or create active conversation
                    $conversation = Conversation::where('customer_id', $customer->id)
                        ->where('platform', $platformName)
                        ->latest()
                        ->first();

                    $isNewConversation = false;

                    if (!$conversation || $conversation->end_at || $conversation->created_at < now()->subHours(config('services.conversation.conversation_expire_hours'))) {
                        if ($conversation) {
                            updateUserInRedis($conversation->agent_id ? User::find($conversation->agent_id) : null, $conversation);
                        }

                        $conversation = Conversation::create([
                            'customer_id' => $customer->id,
                            'platform'    => $platformName,
                            'trace_id'    => "FB-" . now()->format('YmdHis') . '-' . uniqid(),
                        ]);

                        $isNewConversation = true;
                    }

                    // 3️⃣ Handle attachments
                    $storedAttachments = [];
                    $mediaPaths        = [];

                    foreach ($attachments as $attachment) {
                        $downloaded = $this->facebookService->downloadAttachment($attachment);
                        if ($downloaded) {
                            $storedAttachments[] = $downloaded;
                            $mediaPaths[]       = $downloaded['path'];
                        }
                    }

                    // 4️⃣ Store message
                    $message = Message::firstOrCreate(
                        ['platform_message_id' => $platformMessageId],
                        [
                            'conversation_id' => $conversation->id,
                            'sender_id'       => $customer->id,
                            'sender_type'     => Customer::class,
                            'type'            => !empty($attachments) ? 'media' : 'text',
                            'content'         => $text,
                            'direction'       => 'incoming',
                            'receiver_type'   => User::class,
                            'receiver_id'     => $conversation->agent_id ?? null,
                            'parent_id'       => $parentMessageId,
                        ]
                    );

                    // 5️⃣ Save attachments
                    if (!empty($storedAttachments)) {
                        $bulkInsert = array_map(fn($att) => [
                            'message_id'    => $message->id,
                            'attachment_id' => $att['attachment_id'],
                            'path'          => $att['path'],
                            'type'          => $att['type'],
                            'mime'          => $att['mime'],
                            'is_available'  => $att['is_download'],
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ], $storedAttachments);

                        MessageAttachment::insert($bulkInsert);
                    }

                    // 6️⃣ Update conversation
                    $conversation->update(['last_message_id' => $message->id]);

                    // 7️⃣ Prepare payload
                    $payload = [
                        "source"           => "facebook_messenger",
                        "traceId"          => $conversation->trace_id,
                        "conversationId"   => $conversation->id,
                        "conversationType" => $isNewConversation ? "new" : "old",
                        "sender"           => $senderId,
                        "api_key"          => config('dispatcher.messenger_api_key'),
                        "timestamp"        => $timestamp,
                        "message"          => $text ?? null,
                        "attachments"      => $mediaPaths,
                        "subject"          => "Facebook Messenger message from $senderName",
                        "messageId"        => $message->id,
                        "parentMessageId"  => $parentMessageId,
                    ];

                    DB::afterCommit(fn() => $this->sendToDispatcher($payload));
                });
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    // 1. Verify Meta Webhook (GET)
    public function verifyFacebookPageToken(Request $request)
    {
        info(['requestData' => $request->all()]);
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
        Log::info('Website Incoming Request', ['data' => $data]);

        $platform     = Platform::whereRaw('LOWER(name) = ?', ['website'])->first();
        $platformName = strtolower($platform->name);
        $customer     = Customer::find($request->auth_customer->id);

        $payloadsToSend = [];

        DB::transaction(function () use ($customer, $platformName, &$payloadsToSend, $request) {
            // 🔄 Get or create conversation
            $conversation = Conversation::where('customer_id', $customer->id)
                ->where('platform', $platformName)
                // ->where(function ($q) {
                //     $q->whereNull('end_at')
                //         ->orWhere('created_at', '>=', now()->subHours(config('services.conversation.conversation_expire_hours')));
                // })
                ->latest()
                ->first();


            // dd($conversation);

            $isNewConversation = false;

            if (!$conversation || $conversation->end_at || $conversation->created_at < now()->subHours(config('services.conversation.conversation_expire_hours'))) {

                // Clean old conversation from Redis
                if ($conversation) {
                    updateUserInRedis($conversation->agent_id ? User::find($conversation->agent_id) : null, $conversation);
                }

                $conversation = Conversation::create([
                    'customer_id' => $customer->id,
                    'platform'    => $platformName,
                    'trace_id'    => "WEB-" . now()->format('YmdHis') . '-' . uniqid(),
                    'agent_id'    => null,
                ]);
                $isNewConversation = true;
            }

            // 📨 Create the message
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $customer->id,
                'sender_type'     => Customer::class,
                'type'            => 'text',
                'content'         => $request->input('content'),
                'direction'       => 'incoming',
                'receiver_type'   => User::class,
                'receiver_id'     => $conversation->agent_id ?? null,
            ]);

            $attachmentPaths = [];

            // 📎 Save attachments if available
            if ($request->hasFile('attachments')) {
                $bulkInsert = [];

                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('uploads/messages', 'public');
                    $fullPath = '/storage/' . $path;

                    $attachmentPaths[] = $fullPath;

                    $bulkInsert[] = [
                        'message_id' => $message->id,
                        'path'       => $fullPath,
                        'type'       => $file->getClientOriginalExtension(),
                        'mime'       => $file->getClientMimeType(),
                        'size'       => $file->getSize(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                MessageAttachment::insert($bulkInsert);
            }

            // 🔄 Update conversation with last message
            $conversation->update(['last_message_id' => $message->id]);

            $customer->token_expires_at = now()->addMinutes((int)config('services.conversation.website.token_expire_minutes'));
            $customer->save();

            // 📦 Build payload
            $payload = [
                "source"           => "website",
                "traceId"          => $conversation->trace_id,
                "conversationId"   => $conversation->id,
                "conversationType" => $isNewConversation ? "new" : "old",
                "sender"           => $customer->email ?? $customer->phone,
                "api_key"          => config('dispatcher.website_api_key'),
                "timestamp"        => time(),
                "message"          => $message->content,
                "subject"          => "Customer Message from Website",
                "messageId"        => $message->id,
            ];

            if (!empty($attachmentPaths)) {
                $payload['attachments'] = $attachmentPaths;
            }

            $payloadsToSend[] = $payload;

            // 📨 Dispatch payloads after commit
            DB::afterCommit(function () use ($payloadsToSend) {
                foreach ($payloadsToSend as $payload) {
                    Log::info('✅ Dispatching Website Payload After Commit', ['payload' => $payload]);
                    $this->sendToDispatcher($payload); // Define this method or dispatch a job
                }
            });
        });

        return jsonResponse('Message received', true);
    }
}
