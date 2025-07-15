<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\ProcessMessageJob;
use Illuminate\Support\Facades\Log;
use ProcessIncomingMessageJob;

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
}
