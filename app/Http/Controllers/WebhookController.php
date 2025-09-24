<?php

namespace App\Http\Controllers;

use App\Events\SocketIncomingMessage;
use App\Jobs\ProcessWhatsAppMessageBatch;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use ProcessIncomingMessageJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    // ✅ Meta webhook verification endpoint
    public function verify(Request $request)
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

    public function whatsapp(Request $request)
    {
        $data      = $request->all();
        $entry     = $data['entry'][0]['changes'][0]['value'] ?? [];
        $statuses  = $entry['statuses'] ?? [];
        $messages  = $entry['messages'] ?? [];
        $contacts  = $entry['contacts'][0] ?? null;

        // Authenticate Token
        $token = $this->getAuthToken();
        if (!$token) {
            return response()->json(['status' => 'Dispatcher authentication failed'], 500);
        }

        // Get platform
        $platform   = Platform::whereRaw('LOWER(name) = ?', [strtolower('WhatsApp')])->first();
        if (!$platform) {
            return response()->json(['status' => 'platform_not_found'], 500);
        }
        $platformId   = $platform->id;
        $platformName = $platform->name;

        // Prepare phone & customer
        $rawPhone   = $contacts['wa_id'] ?? ($messages[0]['from'] ?? null);
        $phone      = substr($rawPhone, -11); // last 11 digits
        $senderName = $contacts['profile']['name'] ?? 'WhatsApp Customer';

        DB::transaction(function () use ($token, $statuses, $messages, $phone, $senderName, $platformId, $platformName) {

            // Get or create customer
            $customer = Customer::where('phone', $phone)->where('platform_id', $platformId)->first();

            if (!$customer) {
                $customer              = new Customer();
                $customer->phone       = '+88' . $phone;
                $customer->platform_id = $platformId;
                $customer->name        = $senderName;
                $customer->save(); // <-- actually insert into DB
            }

            // Check for existing conversation (last 6 hours) or create new
            $conversation = Conversation::where('customer_id', $customer->id)->where('platform', $platformName)
                ->where(function ($q) {
                    $q->whereNull('end_at')->orWhere('created_at', '>=', now()->subHours(6));
                })
                ->latest()
                ->first();


            $isNewConversation = false;

            if (!$conversation || $conversation->end_at !== null || $conversation->created_at < now()->subHours(6)) {
                $conversation              = new Conversation();
                $conversation->customer_id = $customer->id;
                $conversation->platform    = $platformName;
                $conversation->trace_id    = "WA-" . now()->format('YmdHis') . '-' . uniqid();
                $conversation->agent_id    = null;
                $conversation->save();

                $isNewConversation = true;
            }

            // Step 1: Handle status updates
            foreach ($statuses as $status) {
                $payload = [
                    "source"           => "whatsapp",
                    "traceId"          => "WA-" . now()->format('YmdHis') . '-' . uniqid(),
                    "conversationId"   => $conversation->id,
                    "conversationType" => $isNewConversation ? "new" : "old",
                    "sender"           => $phone,
                    "timestamp"        => $status['timestamp'] ?? time(),
                    "message"          => $status['status'] ?? '',
                    "attachmentId"     => [],
                    "attachments"      => [],
                    "subject"          => "WhatsApp Status Update",
                ];
                $this->sendToHandler($token, $payload);
            }

            // Step 2: Process customer messages
            foreach ($messages as $msg) {
                $type        = $msg['type'] ?? '';
                $caption     = null;
                $mediaIds    = [];
                $attachments = [];
                $timestamp   = $msg['timestamp'] ?? time();

                if ($type === 'text') {
                    $caption = $msg['text']['body'] ?? '';
                } elseif (in_array($type, ['image', 'video', 'document'])) {
                    $mediaId = $msg[$type]['id'] ?? null;
                    if ($mediaId) {
                        $mediaIds[] = $mediaId;
                        $attachments[] = [
                            'attachment_id' => $mediaId,
                            'path'          => $this->getMediaUrl($mediaId),
                            'is_download'   => 0,
                        ];
                    }
                    if (!$caption && !empty($msg[$type]['caption'])) {
                        $caption = $msg[$type]['caption'];
                    }
                }

                // Insert message
                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id'       => $customer->id,
                    'sender_type'     => Customer::class,
                    'type'            => $type,
                    'content'         => $caption ?? null,
                    'direction'       => 'incoming',
                    'receiver_type'   => User::class,
                    'receiver_id'     => $conversation->agent_id,
                ]);

                // Insert attachments
                if (!empty($attachments)) {
                    $bulkInsert = [];
                    foreach ($attachments as $att) {
                        $bulkInsert[] = [
                            'message_id'    => $message->id,
                            'attachment_id' => $att['attachment_id'],
                            'path'          => $att['path'],
                            'is_available'  => $att['is_download'],
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ];
                    }
                    MessageAttachment::insert($bulkInsert);
                }

                // Update conversation last_message_id
                $conversation = Conversation::find($conversation->id);
                $conversation->last_message_id = $message->id;
                $conversation->save();

                info($conversation);
                // Forward payload
                $payload = [
                    "source"           => "whatsapp",
                    "traceId"          => 'wa_' . uniqid(),
                    "conversationId"   => $conversation->id,
                    "conversationType" => $isNewConversation ? "new" : "old",
                    "sender"           => $phone,
                    "timestamp"        => $timestamp,
                    "message"          => $caption ?? 'No text message',
                    "attachmentId"     => $mediaIds,
                    "attachments"      => array_column($attachments, 'path'),
                    "subject"          => "Customer Message from $senderName",
                ];
                $this->sendToHandler($token, $payload);
            }
        });

        return jsonResponse(!empty($messages) ? 'Message received' : 'Status received',  true);
    }
    /**
     * Authenticate and get API token for DISPATCHER 
     */
    private function getAuthToken(): ?string
    {
        $authResponse = Http::post(config('dispatcher.url') . config('dispatcher.endpoints.authenticate'), ['api_key' => config('dispatcher.api_key')]);

        if (!$authResponse->ok()) {
            Log::error('[WHATSAPP ERROR] Authentication failed', ['response' => $authResponse->body()]);
            return null;
        }

        return $authResponse->json('token');
    }

    /**
     * Send payload to handler API
     */
    private function sendToHandler(string $token, array $payload): void
    {
        try {
            $response = Http::withToken($token)->acceptJson()->post(config('dispatcher.url') . config('dispatcher.endpoints.handler'), $payload);

            if ($response->ok()) {
                Log::info("[CUSTOMER MESSAGE FORWARDED]", $payload);
            } else {
                Log::error("[CUSTOMER MESSAGE FORWARDED] FAILED", ['payload' => $payload, 'response' => $response->body()]);
            }
        } catch (\Exception $e) {
            Log::error("[CUSTOMER MESSAGE FORWARDED] ERROR", ['exception' => $e->getMessage()]);
        }
    }

    private function getMediaUrl($mediaId)
    {
        $accessToken = env('WHATSAPP_ACCESS_TOKEN');

        if (!$mediaId) {
            return null;
        }

        $cacheKey = "whatsapp_media_url_{$mediaId}";
        $cachedUrl = Cache::get($cacheKey);
        if ($cachedUrl) {
            return $cachedUrl;
        }

        $url = "https://graph.facebook.com/v18.0/{$mediaId}";

        try {
            $client   = new \GuzzleHttp\Client();
            $response = $client->get($url, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}"
                ]
            ]);

            $body     = json_decode($response->getBody(), true);
            $mediaUrl = $body['url'] ?? null;

            if ($mediaUrl) {
                // Cache media url for 10 minutes to avoid repeated calls
                Cache::put($cacheKey, $mediaUrl, now()->addMinutes(10));
            }

            return $mediaUrl;
        } catch (RequestException $e) {
            Log::error("Error fetching media URL for mediaId {$mediaId}: " . $e->getMessage());
            return null;
        }
    }

    public function fetchWhatsappMedia($mediaId)
    {
        $accessToken = env('WHATSAPP_ACCESS_TOKEN');
        $client      = new \GuzzleHttp\Client();

        try {
            // Get media URL first
            $response = $client->get("https://graph.facebook.com/v18.0/{$mediaId}", [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}"
                ]
            ]);

            $body     = json_decode($response->getBody(), true);
            $mediaUrl = $body['url'] ?? null;

            if (!$mediaUrl) {
                return response()->json(['error' => 'Media URL not found'], 404);
            }

            // Now fetch the actual media content (stream it)
            $mediaResponse = $client->get($mediaUrl, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}"
                ],
                'stream' => true,
            ]);

            // Return streamed response with appropriate headers
            return response($mediaResponse->getBody(), 200)
                ->header('Content-Type', $mediaResponse->getHeaderLine('Content-Type'))
                ->header('Content-Disposition', $mediaResponse->getHeaderLine('Content-Disposition'));
        } catch (\Exception $e) {
            Log::error("Error fetching WhatsApp media: " . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch media'], 500);
        }
    }

    // Meta Webhook verification (GET request)
    public function verifyIntragram(Request $request)
    {
        $verify_token = env('INSTAGRAM_VERIFY_TOKEN'); // Get from .env

        $mode = $request->get('hub_mode');
        $token = $request->get('hub_verify_token');
        $challenge = $request->get('hub_challenge');

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
    public function verifyMessenger(Request $request)
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
    public function receiveMessengerMsg(Request $request)
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
