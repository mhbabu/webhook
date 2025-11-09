<?php

namespace App\Services\Platforms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InstagramService
{
    protected $businessId;

    protected $systemUserToken;

    protected $pageToken;

    protected $verifyToken;

    protected $baseUrl;

    public function __construct()
    {
        // $this->businessId = config('services.instagram.page_id');
        // $this->businessId = '17841475650870617';
        $this->businessId = config('services.instagram.ig_business_id');
        $this->pageToken = config('services.instagram.ig_page_token');
        $this->verifyToken = config('services.instagram.ig_verify_token');
        $this->systemUserToken = config('services.graph.system_user_token');
        $this->baseUrl = config('services.graph.base_url').'/'.config('services.graph.version');

    }

    public function getIgUserInfo($senderId)
    {
        $url = $this->baseUrl."/${senderId}";

        $response = Http::get($url, [
            // 'fields' => 'id,name,username',
            'fields' => 'id,username,name,profile_pic,follower_count,is_verified_user,is_user_follow_business,is_business_follow_user',
            'access_token' => $this->pageToken,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            Log::info('✅ Fetched Instagram user info', [
                'senderId' => $senderId,
                'data' => $data,
            ]);

            return [
                'id' => $data['id'] ?? null,
                'name' => $data['name'] ?? null,
                'username' => $data['username'] ?? null,
                'profile_pic' => $data['profile_pic'] ?? null,
            ];
        }

        Log::warning('⚠️ Failed to fetch Instagram user info', [
            'senderId' => $senderId,
            'response' => $response->body(),
        ]);

        return [];
    }

    public function sendInstagramMessage($recipientId, $message)
    {
        try {
            $accessToken = $this->pageToken;
            // Log::info(['token' => $accessToken]);
            $url = $this->baseUrl.'/me/messages';

            $payload = [
                'messaging_product' => 'instagram',
                'messaging_type' => 'RESPONSE',
                'recipient' => ['id' => $recipientId],
                'message' => ['text' => $message],
            ];

            $response = Http::timeout(30)
                ->withToken($accessToken)
                ->post($url, $payload);

            $responseData = $response->json();
            $statusCode = $response->status();

            Log::info('📤 Instagram Reply Attempt:', [
                'recipient' => $recipientId,
                'message' => $message,
                'status_code' => $statusCode,
                'response' => $responseData,
            ]);

            if ($statusCode === 200 && isset($responseData['message_id'])) {
                Log::info('✅ Instagram message sent successfully');

                return [
                    'success' => true,
                    'message_id' => $responseData['message_id'],
                ];
            } else {
                Log::error('❌ Instagram message failed:', [
                    'error' => $responseData['error'] ?? 'Unknown error',
                ]);

                return [
                    'success' => false,
                    'error' => $responseData['error'] ?? 'Unknown error',
                ];
            }

        } catch (\Exception $e) {
            Log::error('💥 Instagram message exception:', [
                'error' => $e->getMessage(),
                'recipient' => $recipientId,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 🔹 Download attachment and store it locally
     */
    public function downloadAttachment(array $attachment): ?array
    {
        try {
            $type = $attachment['type'] ?? 'file';
            $payload = $attachment['payload'] ?? [];
            $url = $payload['url'] ?? null;

            if (! $url) {
                return null;
            }

            $response = Http::get($url);
            if (! $response->ok()) {
                Log::error("❌ Failed to download Instagram attachment: {$url}");

                return null;
            }

            $mime = $response->header('Content-Type') ?? 'application/octet-stream';
            $extension = match (true) {
                str_contains($mime, 'image/') => explode('/', $mime)[1],
                str_contains($mime, 'video/') => explode('/', $mime)[1],
                str_contains($mime, 'audio/') => explode('/', $mime)[1],
                default => pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'bin',
            };

            $filename = 'attachments/igm_'.uniqid().'.'.$extension;

            Storage::disk('public')->put($filename, $response->body());

            return [
                'attachment_id' => $url,
                'path' => $filename,
                'is_download' => 1,
                'mime' => $mime,
                'type' => $type,
            ];
        } catch (\Exception $e) {
            Log::error('⚠️ Instagram attachment download failed: '.$e->getMessage());

            return null;
        }
    }

    public function sendAttachmentMessage(string $recipientId, string $filePath, ?string $mime = null): array
    {
        try {
            $systemUserToken = $this->systemUserToken;
            $accessToken = $this->pageToken;
            // $uploadUrl = "{$this->baseUrl}/me/message_attachments";
            $uploadUrl = "{$this->baseUrl}/$this->businessId/message_attachments";

            $file = Storage::disk('public')->path($filePath);
            $fileSize = Storage::disk('public')->size($filePath);
            Log::info("📁 Sending Instagram attachment: {$filePath} (Size: {$fileSize} bytes)");

            // Detect MIME if not provided
            if (! $mime) {
                $mime = mime_content_type($file) ?: 'application/octet-stream';
            }

            $type = $this->resolveMediaType($mime);

            // 1️⃣ Upload media to Instagram with proper message payload
            $uploadResponse = Http::attach('filedata', file_get_contents($file), basename($file))
                ->asMultipart()
                ->post($uploadUrl, [
                    'message' => json_encode([
                        'attachment' => [
                            'type' => $type,
                            'payload' => [
                                'is_reusable' => true,
                            ],
                        ],
                    ]),
                    'access_token' => $systemUserToken,
                ]);

            $uploadJson = $uploadResponse->json();

            if (! $uploadResponse->ok() || empty($uploadJson['attachment_id'])) {
                Log::error('❌ Insstagram media upload failed', ['response' => $uploadJson]);

                return ['error' => 'Upload failed', 'response' => $uploadJson];
            }

            $attachmentId = $uploadJson['attachment_id'];

            // 2️⃣ Send message using uploaded attachment
            $sendUrl = "{$this->baseUrl}/$this->businessId/messages";
            // $sendUrl = "{$this->baseUrl}/me/messages";
            $payload = [
                'messaging_product' => 'instagram',
                'recipient' => ['id' => $recipientId],
                'message' => [
                    'attachment' => [
                        'type' => $type,
                        'payload' => [
                            'attachment_id' => $attachmentId,
                            'is_reusable' => true,
                        ],
                    ],
                ],
                'messaging_type' => 'RESPONSE',
            ];

            Log::info('📤 Instagram Send Attachment Message Payload', [
                'payload' => $payload,
            ]);
            // $response = Http::post($sendUrl, $payload);
            $response = Http::withToken($systemUserToken)->post($sendUrl, $payload);

            Log::info('📤 Instagram Send Attachment Message Response', [
                'mime' => $mime,
                'type' => $type,
                'upload_result' => $uploadJson,
                'send_response' => $response->json(),
            ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('⚠️ Instagram sendAttachmentMessage failed: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    public function sendAttachmentMessage1(string $recipientId, string $filePath, ?string $mime = null): array
    {
        try {
            $file = Storage::disk('public')->path($filePath);

            // Detect MIME if not provided
            if (! $mime) {
                $mime = mime_content_type($file) ?: 'application/octet-stream';
            }

            $type = $this->resolveMediaType($mime);

            // Instagram only supports 'image' or 'video' in DMs
            if (! in_array($type, ['image', 'video'])) {
                return ['error' => 'Instagram DM only supports image/video attachments'];
            }

            $igUserId = $recipientId;
            $accessToken = $this->pageToken;

            // 1️⃣ Upload media to Instagram {$this->baseUrl}
            $uploadUrl = "{$this->baseUrl}/{$igUserId}/media";
            $mediaResponse = Http::post($uploadUrl, [
                'image_url' => $type === 'image' ? asset("storage/{$filePath}") : null,
                'video_url' => $type === 'video' ? asset("storage/{$filePath}") : null,
                'access_token' => $accessToken,
            ]);

            $mediaJson = $mediaResponse->json();

            if (! $mediaResponse->ok() || empty($mediaJson['id'])) {
                Log::error('❌ Instagram media upload failed', ['response' => $mediaJson]);

                return ['error' => 'Instagram media upload failed', 'response' => $mediaJson];
            }

            $creationId = $mediaJson['id'];

            // 2️⃣ Send media via DM
            $sendUrl = "{$this->url}/{$igUserId}/messages";
            $payload = [
                'recipient' => ['id' => $recipientId],
                'message' => [
                    'attachment' => [
                        'type' => $type,
                        'payload' => [
                            'id' => $creationId,
                        ],
                    ],
                ],
                'messaging_type' => 'RESPONSE',
                'access_token' => $accessToken,
            ];

            $sendResponse = Http::post($sendUrl, $payload);

            Log::info('📤 Instagram Send Attachment Message Response', [
                'type' => $type,
                'upload_result' => $mediaJson,
                'send_response' => $sendResponse->json(),
            ]);

            return $sendResponse->json();
        } catch (\Exception $e) {
            Log::error('⚠️ Instagram sendAttachmentMessage failed: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * 🔹 Resolve MIME type to a platform media type
     */
    public function resolveMediaType(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            str_starts_with($mime, 'application/') => 'file',
            default => 'file',
        };
    }

    public function postWithImage($message, $imageUrl)
    {
        $url = 'https://graph.instagram.com/v24.0/me/photos';

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
