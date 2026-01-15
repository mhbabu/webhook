<?php

namespace App\Http\Controllers\Api\SocialPage;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostCommentReply;
use App\Models\Customer;
use App\Models\Platform;
use App\Models\Conversation;
use Illuminate\Support\Facades\DB;
use App\Services\SocialPage\FacebookPageService;

use function Laravel\Prompts\info;

class FacebookPagePostSyncController extends Controller
{
    protected FacebookPageService $facebookPageService;

    public function __construct(FacebookPageService $facebookPageService)
    {
        $this->facebookPageService = $facebookPageService;
    }

    /**
     * 🔹 Sync all posts, comments, replies, attachments, and customers
     */
    public function syncSocialPostData()
    {
        $posts = $this->facebookPageService->syncPagePosts();

        foreach ($posts as $fbPost) {
            DB::transaction(function () use ($fbPost) {
                // 1️⃣ Sync post and attachments
                $post = $this->syncPost($fbPost);
                // 2️⃣ Sync comments and replies (also create conversations)
                $this->syncComments($post, $fbPost['comments']['data'] ?? []);
            });
        }

        return jsonResponse('Facebook page posts synchronized successfully', true);
    }

    /**
     * 🔹 Sync post
     */
    protected function syncPost(array $fbPost): Post
    {
        return Post::updateOrCreate(
            ['platform_post_id' => $fbPost['id']],
            [
                'content'       => $fbPost['message'] ?? null,
                'source'        => 'facebook',
                'posted_at'     => $fbPost['created_time'] ?? null,
                'permalink_url' => $fbPost['permalink_url'] ?? null,
                'privacy'       => $fbPost['privacy'] ?? null,
                'location'      => $fbPost['place'] ?? null,
                'tags'          => $fbPost['message_tags'] ?? [],
                'reactions'     => $fbPost['reactions']['summary'] ?? null,
                'post_type'     => $fbPost['status_type'] ?? 'status',
                'attachment'    => $fbPost['attachments']['data'] ?? [],
            ]
        );
    }

    /**
     * 🔹 Sync comments
     */
    protected function syncComments(Post $post, array $comments): void
    {
        foreach ($comments as $fbComment) {
            $customer = $this->getOrCreateCustomer($fbComment['from'] ?? null);

            if (!$customer) continue;

            $comment = PostComment::updateOrCreate(
                ['platform_comment_id' => $fbComment['id']],
                [
                    'post_id'      => $post->id,
                    'customer_id'  => $customer->id,
                    'content'      => $fbComment['message'] ?? null,
                    'commented_at' => $fbComment['created_time'] ?? null,
                    'mentions'     => $fbComment['message_tags'] ?? null,
                    'attachment'   => isset($fbComment['attachment']) ? [$fbComment['attachment']] : [],
                ]
            );

            // 🔹 Create conversation for this comment
            $this->createConversation($customer->id, $post->id, 'comment', $comment->id);

            // Sync replies
            $this->syncReplies($post, $comment, $fbComment['comments']['data'] ?? []);
        }
    }

    /**
     * 🔹 Sync replies
     */
    protected function syncReplies(Post $post, PostComment $comment, array $replies): void
    {
        foreach ($replies as $reply) {
            $customer = $this->getOrCreateCustomer($reply['from'] ?? null);

            if (!$customer) continue;

            $replyModel = PostCommentReply::updateOrCreate(
                ['platform_reply_id' => $reply['id']],
                [
                    'post_comment_id' => $comment->id,
                    'customer_id'     => $customer->id,
                    'content'         => $reply['message'] ?? null,
                    'replied_at'      => $reply['created_time'] ?? null,
                    'mentions'        => $reply['message_tags'] ?? null,
                    'attachment'      => isset($reply['attachment']) ? [$reply['attachment']] : [],
                ]
            );

            // 🔹 Create conversation for this reply
            $this->createConversation($customer->id, $post->id, 'reply', $replyModel->id);
        }
    }

    /**
     * 🔹 Get or create a customer from Facebook user data
     */
    protected function getOrCreateCustomer(?array $fbUser)
    {
        if (!$fbUser || empty($fbUser['id'])) {
            return null; // Skip if no user info
        }

        return Customer::firstOrCreate(
            ['platform_user_id' => $fbUser['id']],
            [
                'name'        => $fbUser['name'] ?? 'Facebook User',
                'platform_id' => $this->getPlatformId(),
            ]
        );
    }

    /**
     * 🔹 Helper to get platform ID
     */
    protected function getPlatformId(): ?int
    {
        return Platform::where('name', 'facebook')->value('id');
    }

    /**
     * 🔹 Create a conversation for comment or reply and send dispatcher payload
     */
    protected function createConversation(int $customerId, int $postId, string $type, int $typeId, ?array $extraData = []): Conversation
    {
        $conversation = Conversation::updateOrCreate(
            [
                'customer_id' => $customerId,
                'post_id'     => $postId,
                'type'        => $type,
                'type_id'     => $typeId,
                'platform'    => 'facebook',
            ],
            [
                'started_at' => now(),
                'trace_id'   => 'FBP-' . now()->format('YmdHis') . '-' . uniqid()
            ]
        );

        // 🔹 Build payload
        $payload = [
            'source'             => 'facebook',
            'traceId'            => $conversation->trace_id,
            'conversationId'     => $conversation->id,
            'ConversationType'   => 'new',
            'api_key'            => config('dispatcher.facebook_api_key'),
            'sender'             => (string)$conversation->customer_id,
            'timestamp'          => now()->timestamp,
            'message'            => Customer::find($customerId)->name . ' ' . ($type === 'comment' ? 'commented on' : 'replied to') . ' your post',
            'attachmentId'       => [],
            'attachments'        => [],
            'subject'            => $conversation->subject,
            'messageId'          => $typeId,
            'parentMessageId'    => $postId,
        ];

        info('Dispatcher Payload:', $payload);
        // 🔹 Send to dispatcher using helper
        sendToDispatcher($payload);

        return $conversation;
    }
}
