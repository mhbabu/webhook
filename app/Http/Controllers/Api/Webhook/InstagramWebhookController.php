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
    public function receiveInstagramMessage2(Request $request)
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
                                $filename = "profile_photos/ig_{$customer->id}." . $extension;
                                Storage::disk('public')->put($filename, $response->body());
                                $customer->update(['profile_photo' => $filename]);
                            }
                        } catch (\Exception $e) {
                            Log::error('⚠️ Failed to download Instagram profile photo: ' . $e->getMessage());
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
                            'trace_id' => 'IGM-' . now()->format('YmdHis') . '-' . uniqid(),
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

                    // Log::info('📎 Normalized Instagram attachment type', [
                    //     'rawType' => $rawType ?? null,
                    //     'normalizedType' => $type,
                    //     'attachments' => $attachments,
                    // ]);

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
                $isEcho = $message['is_echo'] ?? false;
                $timestamp = $event['timestamp'] ?? now()->timestamp;
                $parentMessageId = $message['reply_to']['mid'] ?? null;

                /**
                 * 🚨 CRITICAL FIX — SKIP BUSINESS SENT MESSAGES
                 */
                if ($isEcho || $senderId === $instagramId) {
                    Log::info('🌀 Skipping Instagram echo (business message)', [
                        'senderId' => $senderId,
                        'instagramId' => $instagramId,
                        'mid' => $platformMessageId
                    ]);
                    continue;
                }

                if (! $senderId || ! $platformMessageId) {
                    continue;
                }

                Log::info('📥 Incoming Instagram user message', [
                    'sender' => $senderId,
                    'text' => $text
                ]);

                $existingCustomer = Customer::where('platform_user_id', $senderId)->first();

                if ($existingCustomer) {
                    $username = $existingCustomer->username ?? 'unknown';
                    $senderName = $existingCustomer->name ?? "IG-{$username}-{$senderId}";
                    $profilePic = $existingCustomer->profile_photo ?? null;
                } else {
                    $senderInfo = $this->instagramService->getIgUserInfo($senderId);
                    $username = $senderInfo['username'] ?? 'unknown';
                    $senderName = $senderInfo['name'] ?? "IG-{$username}-{$senderId}";
                    $profilePic = $senderInfo['profile_pic'] ?? null;
                }

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

                    $customer = Customer::firstOrCreate(
                        ['platform_user_id' => $senderId],
                        [
                            'name' => $senderName,
                            'username' => $username,
                            'platform_id' => $platformId,
                        ]
                    );

                    /**
                     * 🔒 Reuse conversation ALWAYS if exists (no accidental new thread)
                     */
                    $conversation = Conversation::where('customer_id', $customer->id)
                        ->where('platform', $platformName)
                        ->latest()
                        ->first();

                    if (! $conversation) {
                        $conversation = Conversation::create([
                            'customer_id' => $customer->id,
                            'platform' => $platformName,
                            'trace_id' => 'IGM-' . now()->format('YmdHis') . '-' . uniqid(),
                        ]);
                    }

                    /**
                     * Attachment normalization
                     */
                    $storedAttachments = [];
                    $mediaPaths = [];
                    $type = 'text';

                    if (! empty($attachments)) {
                        $rawType = strtolower($attachments[0]['type'] ?? 'media');
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
                    }

                    $resolvedParentId = null;
                    if (! empty($parentMessageId)) {
                        $parent = Message::where('platform_message_id', $parentMessageId)->first();
                        $resolvedParentId = $parent ? $parent->id : $parentMessageId;
                    }

                    $message = Message::firstOrCreate(
                        ['platform_message_id' => $platformMessageId],
                        [
                            'conversation_id' => $conversation->id,
                            'sender_id' => $customer->id,
                            'sender_type' => Customer::class,
                            'type' => $type,
                            'content' => $text,
                            'direction' => 'incoming',
                            'receiver_type' => User::class,
                            'receiver_id' => $conversation->agent_id,
                            'parent_id' => $resolvedParentId,
                        ]
                    );

                    if (! empty($storedAttachments)) {
                        MessageAttachment::insert(array_map(fn($att) => [
                            'message_id' => $message->id,
                            'attachment_id' => $att['attachment_id'],
                            'path' => $att['path'],
                            'type' => $att['type'],
                            'mime' => $att['mime'],
                            'is_available' => $att['is_download'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ], $storedAttachments));
                    }

                    $conversation->update(['last_message_id' => $message->id]);

                    DB::afterCommit(function () use ($conversation, $senderId, $text, $mediaPaths, $timestamp, $senderName, $message) {
                        $payload = [
                            'source' => 'instagram_message',
                            'traceId' => $conversation->trace_id,
                            'conversationId' => $conversation->id,
                            'conversationType' => 'old',
                            'sender' => $senderId,
                            'api_key' => config('dispatcher.instagram_api_key'),
                            'timestamp' => $timestamp,
                            'message' => $text,
                            'attachments' => $mediaPaths,
                            'subject' => "Instagram message from $senderName",
                            'messageId' => $message->id,
                        ];

                        Log::info('📤 Dispatching cleaned Instagram message', $payload);
                        sendToDispatcher($payload);
                    });
                });
            }
        }

        return response('EVENT_RECEIVED', 200);
    }


    /**
     * Send payload to dispatcher API
     */
    private function sendToDispatcher(array $payload): void
    {
        try {
            $response = Http::acceptJson()->post(config('dispatcher.url') . config('dispatcher.endpoints.handler'), $payload);

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
