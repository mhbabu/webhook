<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Platform;
use App\Models\User;
use App\Services\Platforms\FacebookPageService;
use App\Services\Platforms\FacebookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FacebookWebhookController extends Controller
{
    protected $facebookService;

    protected $facebookPageService;

    public function __construct(FacebookService $facebookService, FacebookPageService $facebookPageService)
    {
        $this->facebookService = $facebookService;
        $this->facebookPageService = $facebookPageService;
    }

    /**
     * Verify webhook token when Facebook sends GET request
     */
    public function verifyFacebookToken(Request $request)
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

    public function webhook(Request $request, FacebookPageService $pageService)
    {
        $payload = $request->all();

        Log::info('📥 Facebook Webhook Payload', $payload);

        foreach ($payload['entry'] ?? [] as $entry) {

            /**
             * 1️⃣ Messenger Events (Inbox)
             */
            if (! empty($entry['messaging'])) {
                $this->handleMessengerEvents($entry['messaging'], $request);

                continue;
            }

            /**
             * 2️⃣ Page Feed Events (Post / Comment / Reaction)
             */
            if (! empty($entry['changes'])) {
                $this->handlePageFeedEvents($entry['changes'], $pageService);

                continue;
            }

            Log::warning('⚠️ Unknown Facebook entry format', ['entry' => $entry]);
        }

        return response('EVENT_RECEIVED', 200);
    }

    private function handleMessengerEvents(array $messagingEvents, Request $request): void
    {
        Log::info('📩 Messenger Webhook Events', ['count' => count($messagingEvents)]);

        $platform = Platform::whereRaw('LOWER(name) = ?', ['facebook_messenger'])->first();
        $platformId = $platform->id ?? null;
        $platformName = strtolower($platform->name ?? 'facebook_messenger');

        foreach ($messagingEvents as $event) {

            $senderId = $event['sender']['id'] ?? null;
            $pageId = $event['recipient']['id'] ?? null;

            // Skip page → user echoes
            if (! $senderId || $senderId === $pageId) {
                continue;
            }

            Log::info('💬 Messenger Event', ['event' => $event]);

            // 🔁 CALL YOUR EXISTING CODE HERE
            // You can literally paste your existing logic
            // or extract it further into a MessengerService
            $this->processMessengerMessage($event, $platformId, $platformName);
        }
    }

    private function handlePageFeedEvents(array $changes, FacebookPageService $pageService): void
    {
        foreach ($changes as $change) {

            $field = $change['field'] ?? null;
            $value = $change['value'] ?? [];

            Log::info('📰 Page Feed Event', [
                'field' => $field,
                'item' => $value['item'] ?? null,
                'verb' => $value['verb'] ?? null,
            ]);

            if ($field === 'feed') {
                $pageService->handleFeedChange($value);

                continue;
            }

            if ($field === 'comments') {
                $pageService->handleCommentChange($value);

                continue;
            }

            Log::warning('⚠️ Unhandled page field', ['field' => $field]);
        }
    }

    public function postMessage(Request $request)
    {
        // dd('Request received', $request->all());
        $request->validate(['message' => 'required|string']);
        $response = $this->facebookService->postToPage($request->message);

        Log::info('📣 New Post Created: '.json_encode($response));

        return response()->json($response);
    }

    /**
     * Post a message with an image to the Facebook Page.
     */
    public function postWithImage(Request $request)
    {
        // ✅ Validate the input
        $validated = $request->validate([
            'message' => 'required|string',
            'image_url' => 'required|url', // must be a valid public URL
        ]);

        try {
            // ✅ Call the Facebook service
            $response = $this->facebookService->postWithImage(
                $validated['message'],
                $validated['image_url']
            );

            // ✅ Handle success
            return response()->json([
                'success' => true,
                'data' => $response,
            ]);

        } catch (\Exception $e) {
            // ❌ Log and return error
            Log::error('❌ Error posting image to Facebook: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Post a comment on a Facebook post
     */
    public function postComment(Request $request)
    {

        $validated = $request->validate([
            'post_id' => 'required|string',
            'message' => 'required|string',
        ]);

        try {
            $response = $this->facebookService->postComment(
                $validated['message'],
                $validated['post_id']
            );

            Log::info('📣 New Comment Posted: '.json_encode($response));

            return response()->json([
                'success' => true,
                'data' => $response,
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Error posting comment: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Post a reply to an existing Facebook comment (nested comment)
     */
    public function replyComment(Request $request)
    {
        $validated = $request->validate([
            'comment_id' => 'required|string',  // this time we really mean comment_id
            'message' => 'required|string',
        ]);

        try {
            $response = $this->facebookService->replyToComment(
                $validated['message'],
                $validated['comment_id']
            );
            Log::info('↩️ Reply Posted: '.json_encode($response));

            return response()->json([
                'success' => true,
                'data' => $response,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error replying to comment: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
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

    // Receive Message (POST)

    public function incomingFacebookEvent(Request $request)
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
                    if (! $conversation || $conversation->end_at || $conversation->first_message_at < now()->subHours(config('services.conversation.conversation_expire_hours'))) {
                        if ($conversation && $conversation->agent_id) {
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
                                'sender_id'       => $customer->id,
                                'sender_type'     => Customer::class,
                                'type'            => ! empty($attachments) ? 'media' : 'text',
                                'content'         => $finalText,
                                'direction'       => 'incoming',
                                'in_queue_at'     => now(),
                                'receiver_type'   => User::class,
                                'receiver_id'     => $conversation->agent_id ?? null,
                                'parent_id'       => $parentMessageId,
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
                            // 'is_available' => $att['is_download'],
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
}
