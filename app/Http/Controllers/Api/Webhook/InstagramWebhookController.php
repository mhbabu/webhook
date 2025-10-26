<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Platform;
use App\Models\User;
use App\Services\Platforms\InstagramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InstagramWebhookController extends Controller
{
    protected $instagramService;

    public function __construct(InstagramService $instagramService)
    {
        $this->instagramService = $instagramService;
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
                $timestamp = $event['timestamp'] ?? now()->timestamp;
                $messageText = $event['message']['text'] ?? null;

                Log::info('⚠️ Instagram Message event', ['event' => $event]);

                if ($senderId === $instagramId) {
                    Log::info('➡️ Skipped outgoing message from instagram.', ['event' => $event]);

                    continue;
                }

                if (! $senderId) {
                    Log::warning('⚠️ Invalid Instagram DM event: missing senderId', ['event' => $event]);

                    continue;
                }

                if ($senderId && $messageText) {
                    // Send reply
                    $this->sendInstagramMessage($senderId, 'You said: '.$messageText);
                }

                $text = $messageText['text'] ?? null;
                $attachments = $messageText['attachments'] ?? [];
                $platformMessageId = $messageText['mid'] ?? null;
                $replyToMessageId = $messageText['reply_to']['mid'] ?? null;
                $parentMessageId = null;

                if ($replyToMessageId) {
                    $parentMessageId = Message::where('platform_message_id', $replyToMessageId)->value('id');
                }

                // ✅ Fetch sender details from instagram API
                $senderInfo = $this->instagramService->getIgUserInfo($senderId);
                Log::info('👤 Instagram Sender Info:', [
                    'sender_id' => $senderId,
                    'sender_info' => $senderInfo,
                ]);
                $username = $senderInfo['username'] ?? 'unknown';
                $senderName = $senderInfo['name'] ?? "IG-{$username}-{$senderId}";
                $profilePic = $senderInfo['profile_pic'] ?? null;

                DB::transaction(function () use ($senderId, $senderName, $profilePic, $platformId, $platformName, $text, $attachments, $timestamp, $platformMessageId, $parentMessageId) {
                    // 1️⃣ Find or create the customer
                    $customer = Customer::where('platform_user_id', $senderId)->first();
                    if (! $customer) {
                        $customer = Customer::create([
                            'platform_user_id' => $senderId,
                            'name' => $senderName,
                            'platform_id' => $platformId,
                        ]);
                    }

                    // 1️⃣a Download profile photo only when customer is newly created
                    if ($customer->wasRecentlyCreated && $profilePic) {
                        try {
                            $response = Http::get($profilePic);
                            if ($response->ok()) {
                                $extension = pathinfo(parse_url($profilePic, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                                $filename = "profile_photos/fb_{$customer->id}.".$extension;
                                Storage::disk('public')->put($filename, $response->body());
                                $customer->profile_photo = $filename;
                                $customer->save();
                            }
                        } catch (\Exception $e) {
                            Log::error('⚠️ Failed to download Instagram profile photo: '.$e->getMessage());
                        }
                    }

                    // 2️⃣ Find or create an active conversation
                    $conversation = Conversation::where('customer_id', $customer->id)
                        ->where('platform', $platformName)
                        ->where(function ($query) {
                            $query->whereNull('end_at')
                                ->orWhere('created_at', '>=', now()->subHours(config('services.conversation.conversation_expire_hours')));
                        })
                        ->latest()
                        ->first();

                    $isNewConversation = false;

                    if (! $conversation || $conversation->end_at || $conversation->created_at < now()->subHours(config('services.conversation.conversation_expire_hours'))) {
                        $conversation = new Conversation;
                        $conversation->customer_id = $customer->id;
                        $conversation->platform = $platformName;
                        $conversation->trace_id = 'IGM-'.now()->format('YmdHis').'-'.uniqid();
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
                                'conversation_id' => $conversation->id,
                                'sender_id' => $customer->id,
                                'sender_type' => Customer::class,
                                'type' => ! empty($attachments) ? 'media' : 'text',
                                'content' => $text,
                                'direction' => 'incoming',
                                'receiver_type' => User::class,
                                'receiver_id' => $conversation->agent_id ?? null,
                                'parent_id' => $parentMessageId,
                            ]
                        );
                    } catch (\Illuminate\Database\QueryException $e) {
                        if ($e->errorInfo[1] == 1062) {
                            Log::info('⚠️ Duplicate message ignored safely', [
                                'platform_message_id' => $platformMessageId,
                            ]);

                            return; // Skip further processing
                        }
                        throw $e;
                    }

                    // 5️⃣ Attach files to message (if any)
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

                    // 6️⃣ Update conversation with latest message
                    $conversation->update(['last_message_id' => $message->id]);

                    // 7️⃣ Prepare payload
                    $payload = [
                        'source' => 'instagram_message',
                        'traceId' => $conversation->trace_id,
                        'conversationId' => $conversation->id,
                        'conversationType' => $isNewConversation ? 'new' : 'old',
                        'sender' => $senderId,
                        'api_key' => config('dispatcher.instagram_api_key'),
                        'timestamp' => $timestamp,
                        'message' => $text ?? null,
                        'attachments' => $mediaPaths,
                        'subject' => "Instagram message from $senderName",
                        'messageId' => $message->id,
                    ];

                    // ✅ Wait until DB commits successfully before sending payload
                    DB::afterCommit(function () use ($payload) {
                        Log::info('📤 Forwarding Instagram payload (after commit)', ['payload' => $payload]);
                        $this->sendToDispatcher($payload);
                    });
                });
            } // <-- ✅ closes inner foreach
        } // <-- ✅ closes outer foreach

        return response('EVENT_RECEIVED', 200);
    }

    protected function sendInstagramMessage($recipientId, $message)
    {
        $accessToken = env('INSTAGRAM_GRAPH_TOKEN'); // Page access token
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
}
