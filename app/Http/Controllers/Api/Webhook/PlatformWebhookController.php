<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Events\SocketIncomingMessage;
use App\Http\Controllers\Controller;
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

    public function incomingWhatsAppMessage(Request $request)
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
        $rawPhone   = $contacts['wa_id'] ?? ($messages[0]['from'] ?? null);
        if (!$rawPhone) {
            Log::warning('Invalid or missing phone number in request.');
            return response()->json(['status' => 'invalid_phone'], 400);
        }

        $phone      = '+88' . substr($rawPhone, -11); // normalize to last 11 digits
        $senderName = $contacts['profile']['name'] ?? $phone;

        DB::transaction(function () use ($statuses, $messages, $phone, $senderName, $platformId, $platformName) {

            // Get or create customer
            $customer = Customer::firstOrCreate(['phone' => $phone, 'platform_id' => $platformId], ['name' => $senderName]);

            // Get or create conversation
            $conversation = Conversation::where('customer_id', $customer->id)
                ->where('platform', $platformName)
                ->where(function ($q) {
                    $q->whereNull('end_at')
                        ->orWhere('created_at', '>=', now()->subHours(6));
                })
                ->latest()
                ->first();

            $isNewConversation = false;

            if (!$conversation || $conversation->end_at !== null || $conversation->created_at < now()->subHours(6)) {
                $conversation = new Conversation();
                $conversation->customer_id = $customer->id;
                $conversation->platform    = $platformName;
                $conversation->trace_id    = "WA-" . now()->format('YmdHis') . '-' . uniqid();
                $conversation->agent_id    = null;
                $conversation->save();

                $isNewConversation = true;
            }

            // Handle statuses (e.g. delivered, read)
            foreach ($statuses as $status) {
                $payload = [
                    "source"           => "whatsapp",
                    "traceId"          => $conversation->trace_id,
                    "conversationId"   => $conversation->id,
                    "conversationType" => $isNewConversation ? "new" : "old",
                    "sender"           => $phone,
                    "api_key"          => config('dispatcher.api_key'),
                    "timestamp"        => $status['timestamp'] ?? time(),
                    "message"          => $status['status'] ?? '',
                    "attachmentId"     => [],
                    "attachments"      => [],
                    "subject"          => "WhatsApp Status Update",
                ];

                Log::info('Sending WhatsApp Status Payload', ['payload' => $payload]);
                $this->sendToHandler($payload);
            }

            // Handle incoming messages
            foreach ($messages as $msg) {
                $type        = $msg['type'] ?? '';
                $caption     = null;
                $mediaIds    = [];
                $attachments = [];
                $timestamp   = $msg['timestamp'] ?? time();

                // Extract text or media
                if ($type === 'text') {
                    $caption = $msg['text']['body'] ?? '';
                } elseif (in_array($type, ['image', 'video', 'document', 'audio'])) {
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

                // Get WhatsApp platform message ID
                $platformMessageId = $msg['id'] ?? null;

                // Check for reply context
                $parentPlatformMessageId = $msg['context']['id'] ?? null;
                $parentId = null;

                if ($parentPlatformMessageId) {
                    $parent = Message::where('platform_message_id', $parentPlatformMessageId)->first();
                    if ($parent) {
                        $parentId = $parent->id;
                    }
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
                    $bulkInsert = array_map(function ($att) use ($message) {
                        return [
                            'message_id'    => $message->id,
                            'attachment_id' => $att['attachment_id'],
                            'path'          => $att['path'],
                            'type'          => $att['type'],
                            'mime'          => $att['mime'],
                            'size'          => $att['size'],
                            'is_available'  => $att['is_download'],
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ];
                    }, $attachments);

                    MessageAttachment::insert($bulkInsert);
                }

                // Update conversation with last message
                $conversation->last_message_id = $message->id;
                $conversation->save();

                // Build payload
                $payload = [
                    "source"           => "whatsapp",
                    "traceId"          => 'wa_' . uniqid(),
                    "conversationId"   => $conversation->id,
                    "conversationType" => $isNewConversation ? "new" : "old",
                    "sender"           => $phone,
                    "api_key"          => config('dispatcher.api_key'),
                    "timestamp"        => $timestamp,
                    "message"          => $caption ?? 'No text message',
                    "attachmentId"     => $mediaIds,
                    "attachments"      => array_column($attachments, 'path'),
                    "subject"          => "Customer Message from $senderName",
                    "messageId"        => $message->id,
                ];

                Log::info('Sending WhatsApp Message Payload', ['payload' => $payload]);
                $this->sendToHandler($payload);
            }
        });

        return jsonResponse(!empty($messages) ? 'Message received' : 'Status received', true);
    }

    /**
     * Send payload to handler API
     */
    private function sendToHandler(array $payload): void
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
    public function incomingMessengerMessage(Request $request)
    {
        Log::info('📩 Messenger Webhook Payload:', ['data' => $request->all()]);

        $entries = $request->input('entry', []);

        $platform = Platform::whereRaw('LOWER(name) = ?', ['facebook'])->first();
        $platformId = $platform->id ?? null;
        $platformName = strtolower($platform->name ?? 'facebook');

        foreach ($entries as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                $senderId     = $event['sender']['id'] ?? null;
                $pageId       = $event['recipient']['id'] ?? null;
                $timestamp    = $event['timestamp'] ?? now()->timestamp;
                $messageData  = $event['message'] ?? [];

                // 🛑 Skip outgoing messages (from the business page)
                if ($senderId === $pageId) {
                    Log::info('➡️ Skipped outgoing message from page.', ['event' => $event]);
                    continue;
                }

                if (!$senderId) {
                    Log::warning('⚠️ Invalid or empty Messenger event.', ['event' => $event]);
                    continue;
                }

                $text               = $messageData['text'] ?? null;
                $attachments        = $messageData['attachments'] ?? [];
                $platformMessageId  = $messageData['mid'] ?? null;
                $replyToMessageId   = $messageData['reply_to']['mid'] ?? null; // ✅ Get Facebook reply_to.mid
                $parentMessageId    = null;

                // ✅ Lookup internal parent message ID if reply_to exists
                if ($replyToMessageId) {
                    $parentMessageId = Message::where('platform_message_id', $replyToMessageId)->value('id');
                }

                // ✅ Skip duplicate messages
                if (isset($platformMessageId) && Message::where('platform_message_id', $platformMessageId)->exists()) {
                    Log::info("⚠️ Duplicate message skipped", ['platform_message_id' => $platformMessageId, 'sender_id' => $senderId]);
                    continue;
                }

                // Fetch sender details from Facebook API
                $senderInfo = $this->facebookService->getSenderInfo($senderId);
                $senderName = $senderInfo['name'] ?? "Facebook User {$senderId}";
                $profilePic = $senderInfo['profile_pic'] ?? null;

                // 🔒 Start DB transaction
                DB::transaction(function () use ($senderId, $senderName, $profilePic, $platformId, $platformName, $text, $attachments, $timestamp, $platformMessageId, $replyToMessageId, $parentMessageId) {
                    // 1️⃣ Create or fetch customer
                    $customer = Customer::firstOrCreate(
                        ['platform_user_id' => $senderId, 'platform_id' => $platformId],
                        ['name' => $senderName]
                    );

                    // 1️⃣a Download profile photo (on first creation only)
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
                        ->where(function ($query) {
                            $query->whereNull('end_at')
                                ->orWhere('created_at', '>=', now()->subHours(config('services.conversation_expire_hours')));
                        })
                        ->latest()
                        ->first();

                    $isNewConversation = false;

                    if (!$conversation || $conversation->end_at || $conversation->created_at < now()->subHours(6)) {
                        $conversation = new Conversation();
                        $conversation->customer_id = $customer->id;
                        $conversation->platform = $platformName;
                        $conversation->trace_id = "FB-" . now()->format('YmdHis') . '-' . uniqid();
                        $conversation->save();
                        $isNewConversation = true;
                    }

                    // 3️⃣ Handle attachments
                    $storedAttachments = [];
                    $mediaPaths = [];

                    foreach ($attachments as $attachment) {
                        $downloaded = $this->facebookService->downloadAttachment($attachment);
                        if ($downloaded) {
                            $storedAttachments[] = $downloaded;
                            $mediaPaths[] = $downloaded['path'];
                        }
                    }

                    // 4️⃣ Create message
                    $message = Message::create([
                        'conversation_id'     => $conversation->id,
                        'sender_id'           => $customer->id,
                        'sender_type'         => Customer::class,
                        'type'                => !empty($attachments) ? 'media' : 'text',
                        'content'             => $text,
                        'direction'           => 'incoming',
                        'receiver_type'       => User::class,
                        'receiver_id'         => $conversation->agent_id ?? null,
                        'platform_message_id' => $platformMessageId,
                        'reply_to_message_id' => $replyToMessageId,  // ✅ Save Facebook message ID
                        'parent_id'           => $parentMessageId,   // ✅ Save internal parent message ID
                    ]);

                    // 5️⃣ Save attachments (if any)
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

                    // 6️⃣ Update conversation with last message
                    $conversation->last_message_id = $message->id;
                    $conversation->save();

                    // 7️⃣ Prepare payload to forward
                    $payload = [
                        "source"           => "facebook",
                        "traceId"          => $conversation->trace_id,
                        "conversationId"   => $conversation->id,
                        "conversationType" => $isNewConversation ? "new" : "old",
                        "sender"           => $senderId,
                        "api_key"          => config('dispatcher.facebook_api_key'),
                        "timestamp"        => $timestamp,
                        "message"          => $text ?? 'No text message',
                        "attachments"      => $mediaPaths,
                        "subject"          => "Facebook Message from $senderName",
                        "messageId"        => $message->id,
                    ];

                    Log::info('📤 Forwarding Facebook payload', ['payload' => $payload]);
                    $this->sendToHandler($payload);
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
    public function receiveFacebookPageEventData2(Request $request)
    {
        Log::info('📩 Messenger Webhook Payload:', ['data' => $request->all()]);

        return response('EVENT_RECEIVED', 200);
    }

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
}
