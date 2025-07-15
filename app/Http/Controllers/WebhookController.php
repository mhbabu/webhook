<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\ProcessMessageJob;
use Illuminate\Support\Facades\Log;
use ProcessIncomingMessageJob;
use Illuminate\Support\Facades\Http;

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

    // ✅ Webhook message receiver
    public function whatsapp2(Request $request)
    {
        // You can log the incoming payload for debugging
        // \Log::info('WhatsApp webhook received:', $request->all());

        // Dispatch to background job to avoid delay
        ProcessIncomingMessageJob::dispatch($request->all());

        return response()->json(['status' => 'received'], 200);
    }

    public function whatsapp(Request $request)
    {
        // dd(request()->all());
        $data = $request->all(); // Get the full JSON payload

        Log::info('Received WhatsApp message:', $data);

        // Try to extract the message
        $message = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? null;

        if ($message) {
            $from = $message['from']; // User phone number
            $text = $message['text']['body'] ?? '';
            Log::info("User $from says: $text");
        }

        return response()->json(['status' => 'ok']);
    }

    // Meta Webhook verification (GET request)
    public function verifyInstagram(Request $request)
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
    public function receiveInstagramMsg(Request $request)
    {
        Log::info('📩 Instagram Webhook Data:', $request->all());

        // Dispatch job or process data
        return response('EVENT_RECEIVED', 200);
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
