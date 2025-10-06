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
use App\Services\Platforms\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    protected $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
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
        $platformName = $platform->name;

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
                    "traceId"          => "WA-" . now()->format('YmdHis') . '-' . uniqid(),
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
        Log::info('Messenger Webhook Payload:', $request->all());

        $entries = $request->input('entry', []);
        foreach ($entries as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                if (isset($event['message']['text'])) {
                    $senderId = $event['sender']['id'];
                    $text = $event['message']['text'];

                    $this->sendMessage($senderId, "You said: " . $text);
                }
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    // 3. Send Message Back (POST to Messenger API)
    private function sendMessage($recipientId, $messageText)
    {
        $token = env('FB_PAGE_ACCESS_TOKEN');

        $url = 'https://graph.facebook.com/v18.0/me/messages';

        $response = Http::post($url, [
            'recipient' => ['id' => $recipientId],
            'message' => ['text' => $messageText],
            'messaging_type' => 'RESPONSE',
            'access_token' => $token,
        ]);

        Log::info('Sent message response', ['response' => $response->json()]);
    }

    public function websocketTestMethod(Request $request)
    {
        $data = [
            'message_id' => 1,
            'agent_id'   => 2,
            'status'     => 'Processed'
        ];

        SocketIncomingMessage::dispatch($data);
        return jsonResponse('Data dispated the channel', 200);
    }
}
