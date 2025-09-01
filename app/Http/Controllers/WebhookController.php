<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppMessageBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use ProcessIncomingMessageJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Exception\RequestException;

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
        $data = $request->all();

        // Debug raw payload only in development
        if (config('app.debug')) {
            Log::debug('Raw WhatsApp Webhook:', $data);
        }

        $entry     = $data['entry'][0]['changes'][0]['value'] ?? [];
        $statuses  = $entry['statuses'] ?? [];
        $messages  = $entry['messages'] ?? [];
        $contacts  = $entry['contacts'][0] ?? null;
        $to        = $entry['metadata']['display_phone_number'] ?? null;

        // -------------------------
        // ✅ Case 1: Status updates
        // -------------------------
        if (!empty($statuses)) {
            foreach ($statuses as $status) {
                $response = [
                    "source"       => "whatsapp",
                    "traceId"      => 'wa_' . uniqid(),
                    "messageId"    => $status['id'] ?? null,
                    "sender"       => null,
                    "sentTo"       => $status['recipient_id'] ?? null,
                    "parentId"     => null,
                    "timestamp"    => $status['timestamp'] ?? null,
                    "message"      => $status['status'] ?? '',
                    "attachmentId" => [],
                    "attachments"  => [],
                    "subject"      => "WhatsApp Status Update",
                ];

                Log::info('[WHATSAPP STATUS]', $response);
            }
            return response()->json(['status' => 'status_received']);
        }

        // ---------------------------------------
        // ✅ Case 2: Incoming customer messages
        // ---------------------------------------
        if (!empty($messages)) {
            // fallback if contacts not present
            $sender     = $contacts['wa_id'] ?? ($messages[0]['from'] ?? null);
            $senderName = $contacts['profile']['name'] ?? ($messages[0]['from'] ?? 'Unknown');

            foreach ($messages as $msg) {
                $type        = $msg['type'] ?? '';
                $from        = $msg['from'] ?? $sender;
                $messageId   = $msg['id'] ?? null;
                $timestamp   = $msg['timestamp'] ?? null;
                $parentId    = $msg['context']['id'] ?? null;
                $caption     = null;
                $mediaIds    = [];
                $attachments = [];

                // Handle text messages
                if ($type === 'text') {
                    $caption = $msg['text']['body'] ?? '';
                }
                // Handle media messages
                elseif (in_array($type, ['image', 'video', 'document'])) {
                    $mediaId = $msg[$type]['id'] ?? null;
                    if ($mediaId) {
                        $mediaIds[] = $mediaId;
                        $attachments[] = [
                            "type" => $type,
                            "id"   => $mediaId,
                            "url"  => $this->getMediaUrl($mediaId)
                        ];
                    }
                    if (!$caption && !empty($msg[$type]['caption'])) {
                        $caption = $msg[$type]['caption'];
                    }
                }

                // Normalized payload
                $response = [
                    "source"       => "whatsapp",
                    "traceId"      => 'wa_' . uniqid(),
                    "messageId"    => $messageId,
                    "sender"       => $from,
                    "sentTo"       => $to,
                    "parentId"     => $parentId,
                    "timestamp"    => $timestamp,
                    "message"      => $caption ?? 'No message content',
                    "attachmentId" => $mediaIds,
                    "attachments"  => $attachments,
                    "subject"      => "Customer Message from $senderName",
                ];

                Log::info('[CUSTOMER MESSAGE]', $response);
            }

            return response()->json(['status' => 'message_received']);
        }

        // -------------------------
        // ✅ Case 3: No relevant payload
        // -------------------------
        return response()->json(['status' => 'ignored']);
    }


    // ✅ Webhook message receiver
    public function whatsapp2(Request $request)
    {
        $data = $request->all();
        Log::info('Received WhatsApp message:', $data);

        $entry    = $data['entry'][0]['changes'][0]['value'] ?? [];
        $messages = $entry['messages'] ?? [];
        // Removed $contacts and $senderName entirely

        $from        = null;
        $messageId   = null;
        $timestamp   = 0;
        $to          = $entry['metadata']['display_phone_number'] ?? null;
        $mediaIds    = [];
        $attachments = [];
        $caption     = null;
        $parentId    = null;

        foreach ($messages as $msg) {
            $type = $msg['type'] ?? '';
            $from = $msg['from'] ?? $from;
            $messageId = $msg['id'] ?? $messageId;
            $timestamp = isset($msg['timestamp']) ? (int)$msg['timestamp'] : $timestamp;
            $parentId = $msg['context']['id'] ?? $parentId;

            if (in_array($type, ['image', 'video', 'document'])) {
                $mediaId = $msg[$type]['id'] ?? null;
                $mediaUrl = $this->getMediaUrl($mediaId);

                if ($mediaId && $mediaUrl) {
                    $mediaIds[] = $mediaId;
                    $attachments[] = $mediaUrl;
                }

                if (!$caption && !empty($msg[$type]['caption'])) {
                    $caption = $msg[$type]['caption'];
                }
            } elseif ($type === 'text') {
                $caption = $msg['text']['body'] ?? $caption;
            }
        }

        $response = [
            "Source"       => "WHATSAPP",
            "TraceId"      => 'wa' . uniqid(),
            "MessageId"    => $messageId,
            "Sender"       => $from,
            "SentTo"       => $to,
            "ParentId"     => $parentId,
            "Timestamp"    => $timestamp,
            "Message"      => $caption ?? 'No message content',
            "AttachmentId" => $mediaIds,
            "Attachments"  => $attachments,
            "Subject"      => "Product Information request"
        ];

        Log::info('Processed WhatsApp message response:', $response);

        return response()->json($response);
    }

    public function whatsapp3(Request $request)
    {
        $data = $request->all();
        Log::info('Received WhatsApp message:', $data);

        $entry = $data['entry'][0]['changes'][0]['value'] ?? [];
        $messages = $entry['messages'] ?? [];
        $contacts = $entry['contacts'][0] ?? null;

        $from        = null;
        $messageId   = null;
        $timestamp   = 0;
        $to          = $entry['metadata']['display_phone_number'] ?? null;
        $senderName  = $contacts['profile']['name'] ?? 'Unknown';
        $mediaIds    = [];
        $attachments = [];
        $caption     = null;

        foreach ($messages as $msg) {
            $type = $msg['type'] ?? '';
            $from = $msg['from'] ?? $from;
            $messageId = $msg['id'] ?? $messageId;
            $timestamp = isset($msg['timestamp']) ? (int)$msg['timestamp'] : $timestamp;

            if (in_array($type, ['image', 'video', 'document'])) {
                $mediaId = $msg[$type]['id'] ?? null;
                $mediaUrl = $this->getMediaUrl($mediaId);

                if ($mediaId && $mediaUrl) {
                    $mediaIds[] = $mediaId;
                    $attachments[] = $mediaUrl;
                }

                if (!$caption && !empty($msg[$type]['caption'])) {
                    $caption = $msg[$type]['caption'];
                }
            } elseif ($type === 'text') {
                $caption = $msg['text']['body'] ?? $caption;
            }
        }

        // Cache key per sender (wa_id)
        $cacheKey = "whatsapp_thread_{$from}";
        $cacheTTL = 30; // seconds

        $cachedData = Cache::get($cacheKey, [
            'texts'         => [],
            'attachmentIds' => [],
            'attachments'   => [],
            'lastTimestamp' => 0,
        ]);

        if ($caption) {
            $cachedData['texts'][] = $caption;
        }
        if ($mediaIds) {
            $cachedData['attachmentIds'] = array_merge($cachedData['attachmentIds'], $mediaIds);
            $cachedData['attachments']   = array_merge($cachedData['attachments'], $attachments);
        }
        $cachedData['lastTimestamp'] = $timestamp;

        // Save updated cache
        Cache::put($cacheKey, $cachedData, $cacheTTL);

        // ✅ Debounced Job Dispatch (prevent multiple jobs)
        $lockKey = "lock_whatsapp_batch_job_{$from}";
        $lockTTL = 10; // Prevent multiple dispatches within 10s
        if (Cache::add($lockKey, true, $lockTTL)) {
            ProcessWhatsAppMessageBatch::dispatch($from)->delay(now()->addSeconds(5));
        }

        // Return immediate response
        return response()->json(['status' => 'received']);
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
}
