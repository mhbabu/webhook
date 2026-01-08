<?php

namespace App\Services\SocialPage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookPageService
{
    protected string $url;
    protected string $token;
    protected string $pageId;

    public function __construct()
    {
        $this->url    = config('services.facebook.url');
        $this->token  = config('services.facebook.token');
        $this->pageId = config('services.facebook.page_id');
    }

    /**
     * 🔹 ENTRY POINT
     */
    public function syncPagePosts()
    {
        return $this->fetchPosts($this->pageId);
    }

    /**
     * 🔹 Fetch posts
     */
    protected function fetchPosts(string $pageId): array
    {
        $response = Http::get("{$this->url}/{$pageId}/posts", [
            'fields' => implode(',', [
                'id',
                'message',
                'created_time',
                'permalink_url',
                'privacy',
                'status_type',
                'place',
                'message_tags',
                // Full attachments
                'attachments{media_type,url,media{image,source},subattachments{media_type,url,media{image,source}}}',
                // 'attachments{subattachments{media,type,url}}',
                'comments.limit(100){id,message,from,created_time,attachment,message_tags,comments.limit(50){id,message,from,created_time,attachment,message_tags}}',
                'reactions.summary(true)',
            ]),
            'access_token' => $this->token,
        ]);

        return $response->json('data', []);
    }

    /** 🔹 Fetch full user info by Facebook ID */
    public function fetchUser(string $facebookUserId): array
    {
        try {
            $response = Http::get("{$this->url}/{$facebookUserId}", ['fields' => 'id,name,profile_pic', 'access_token' => $this->token]);
            return $response->json();
        } catch (\Exception $e) {
            Log::error("Failed to fetch Facebook user info: {$e->getMessage()}");
            return ['id' => $facebookUserId, 'name' => 'Facebook User'];
        }
    }


    /**
     * 🔹 Get Facebook Page Posts with ALL attachments
     */
    public function getPagePosts(int $limit = 10): array
    {
        $response = Http::get("{$this->url}/{$this->pageId}/posts", [
            'fields' => implode(',', [
                'id',
                'message',
                'story',
                'created_time',
                'permalink_url',
                'status_type',

                // Full attachments
                'attachments{media_type,url,media{image,source},subattachments{media_type,url,media{image,source}}}',

                // Counts
                'shares',
                'likes.summary(true)',
                'comments.summary(true)',

                // Reaction breakdown
                'reactions.type(LIKE).summary(true).as(like)',
                'reactions.type(LOVE).summary(true).as(love)',
                'reactions.type(HAHA).summary(true).as(haha)',
                'reactions.type(WOW).summary(true).as(wow)',
                'reactions.type(SAD).summary(true).as(sad)',
                'reactions.type(ANGRY).summary(true).as(angry)',
            ]),
            'limit' => $limit,
            'access_token' => $this->token,
        ]);

        if ($response->failed()) {
            Log::error('Facebook getPagePosts failed', $response->json());
            return [];
        }

        return $response->json('data', []);
    }

    /**
     * 🔹 Get only Facebook Page Post IDs
     */
    public function getPagePostIds(int $limit = 10): array
    {
        $response = Http::get("{$this->url}/{$this->pageId}/posts", [
            'fields'       => 'id',
            'limit'        => $limit,
            'access_token' => $this->token,
        ]);

        if ($response->failed()) {
            Log::error('Facebook getPagePostIds failed', $response->json());
            return [];
        }

        return collect($response->json('data', []))
            ->pluck('id')
            ->toArray();
    }

    /**
     * 🔹 Fetch full Facebook post by Post ID
     */
    public function getPostById(string $postId): array
    {
        $response = Http::get("{$this->url}/{$postId}", [
            'fields' => implode(',', [
                'id',
                'message',
                'created_time',
                'permalink_url',
                'status_type',
                'place',
                'message_tags',

                // Attachments
                'attachments{subattachments{media,type,url}}',

                // Comments + Replies
                'comments.limit(100){' .
                    'id,message,from,created_time,attachment,message_tags,' .
                    'comments.limit(50){id,message,from,created_time,attachment,message_tags}' .
                    '}',
            ]),
            'access_token' => $this->token,
        ]);

        if ($response->failed()) {
            Log::error('Facebook getPostById failed', [
                'post_id' => $postId,
                'error'   => $response->json(),
            ]);
            return [];
        }

        return $response->json();
    }

    /**
     * 🔹 Reply to a comment on a post
     *
     * @param string $commentId The ID of the comment to reply to
     * @param string $message   The reply message
     * @return array
     */
    public function replyToComment(string $commentId, string $message): array
    {
        try {
            $response = Http::asForm()->post("{$this->url}/{$commentId}/comments", [
                'message'      => $message,
                'access_token' => $this->token,
            ]);

            if ($response->failed()) {
                Log::error('Facebook replyToComment failed', [
                    'comment_id' => $commentId,
                    'message'    => $message,
                    'response'   => $response->json(),
                ]);
                return [
                    'success' => false,
                    'error'   => $response->json(),
                ];
            }

            return [
                'success' => true,
                'data'    => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error("Facebook replyToComment exception: {$e->getMessage()}", [
                'comment_id' => $commentId,
                'message'    => $message,
            ]);

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }
}
