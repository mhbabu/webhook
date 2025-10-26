<?php

namespace App\Services\Platforms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramService
{
    protected $pageId;

    protected $pageToken;

    public function __construct()
    {
        $this->pageId = config('services.instagram.page_id');
        $this->pageToken = config('services.instagram.page_token');
        $this->verifyToken = config('services.instagram.verify_token');
    }

    public function getIgUserInfo($senderId)
    {
        $url = "https://graph.facebook.com/v24.0/{$senderId}";

        $response = Http::get($url, [
            'fields' => 'id,name,username,profile_picture_url',
            'access_token' => env('INSTAGRAM_GRAPH_TOKEN'),
        ]);

        if ($response->successful()) {
            $data = $response->json();

            return [
                'id' => $data['id'] ?? null,
                'name' => $data['name'] ?? null,
                'username' => $data['username'] ?? null,
                'profile_pic' => $data['profile_picture_url'] ?? null,
            ];
        }

        Log::warning('⚠️ Failed to fetch Instagram user info', [
            'senderId' => $senderId,
            'response' => $response->body(),
        ]);

        return [];
    }

    public function sendMessage($message)
    {
        $url = "https://graph.instagram.com/v24.0/{$this->pageId}/feed";

        $response = Http::post($url, [
            'message' => $message,
            'access_token' => $this->pageToken,
        ]);

        return $response->json();
    }

    public function postWithImage($message, $imageUrl)
    {
        $url = "https://graph.instagram.com/v24.0/{$this->pageId}/photos";

        $response = Http::post($url, [
            'url' => $imageUrl,
            'caption' => $message,
            'access_token' => $this->pageToken,
        ]);

        return $response->json();
    }

    public function postComment(string $message, string $postId)
    {
        $url = "https://graph.instagram.com/v24.0/{$postId}/comments";

        $response = Http::post($url, [
            'message' => $message,
            'access_token' => $this->pageToken,
        ]);

        // Optional: handle errors gracefully
        if ($response->failed()) {
            Log::error('❌ Failed to post comment', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response->json();
    }

    public function replyToComment(string $message, string $commentId)
    {
        $url = "https://graph.instagram.com/v24.0/{$commentId}/comments";

        $response = Http::post($url, [
            'message' => $message,
            'access_token' => $this->pageToken,
        ]);

        if ($response->failed()) {
            Log::error('❌ Failed to reply to comment', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response->json();
    }
}
