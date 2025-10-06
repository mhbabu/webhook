<?php

namespace App\Services\Platforms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FacebookService
{
    protected string $url;
    protected string $token;

    public function __construct()
    {
        $this->url   = config('services.facebook.url');
        $this->token = config('services.facebook.token');
    }

    /**
     * 🔹 Fetch sender info (name, profile picture)
     */
    public function getSenderInfo(string $senderId): array
    {
        try {
            $response = Http::get("{$this->url}/{$senderId}", [
                'fields' => 'name,profile_pic',
                'access_token' => $this->token,
            ]);

            if ($response->ok()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error("❌ Facebook getSenderInfo failed: " . $e->getMessage());
        }

        return [];
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

            if (!$url) {
                return null;
            }

            $response = Http::get($url);
            if (!$response->ok()) {
                Log::error("❌ Failed to download Facebook attachment: {$url}");
                return null;
            }

            $mime = $response->header('Content-Type') ?? 'application/octet-stream';
            $extension = match (true) {
                str_contains($mime, 'image/') => explode('/', $mime)[1],
                str_contains($mime, 'video/') => explode('/', $mime)[1],
                str_contains($mime, 'audio/') => explode('/', $mime)[1],
                default => pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'bin',
            };

            $filename = "attachments/fb_" . uniqid() . "." . $extension;

            Storage::disk('public')->put($filename, $response->body());

            return [
                'attachment_id' => $url,
                'path'          => $filename,
                'is_download'   => 1,
                'mime'          => $mime,
                'type'          => $type,
            ];
        } catch (\Exception $e) {
            Log::error("⚠️ Facebook attachment download failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 🔹 Send text message to Facebook user
     */
    public function sendTextMessage(string $recipientId, string $message): array
    {
        $url = "{$this->url}/me/messages";

        $payload = [
            'recipient' => ['id' => $recipientId],
            'message' => ['text' => $message],
            'messaging_type' => 'RESPONSE',
            'access_token' => $this->token,
        ];

        $response = Http::post($url, $payload);

        Log::info('📤 Facebook Send Text Message Response', ['response' => $response->json()]);

        return $response->json();
    }

    /**
     * 🔹 Send attachment message (image, video, audio, file)
     */
    public function sendAttachmentMessage(string $recipientId, string $filePath, ?string $mime = null): array
    {
        try {
            $uploadUrl = "{$this->url}/me/message_attachments";
            $file = Storage::disk('public')->path($filePath);

            // Determine type based on MIME
            if (!$mime) {
                $mime = mime_content_type($file) ?: 'application/octet-stream';
            }

            $type = match (true) {
                str_contains($mime, 'image/') => 'image',
                str_contains($mime, 'video/') => 'video',
                str_contains($mime, 'audio/') => 'audio',
                default => 'file',
            };

            // 1️⃣ Upload file to Facebook
            $uploadResponse = Http::attach('filedata', file_get_contents($file), basename($file))
                ->asMultipart()
                ->post($uploadUrl, [
                    'message_type' => 'RESPONSE',
                    'access_token' => $this->token,
                    'type'         => $type,
                ]);

            $uploadJson = $uploadResponse->json();

            if (!$uploadResponse->ok() || empty($uploadJson['attachment_id'])) {
                Log::error('❌ Facebook media upload failed', ['response' => $uploadJson]);
                return ['error' => 'Upload failed', 'response' => $uploadJson];
            }

            $attachmentId = $uploadJson['attachment_id'];

            // 2️⃣ Send message using uploaded attachment
            $sendUrl = "{$this->url}/me/messages";
            $payload = [
                'recipient' => ['id' => $recipientId],
                'message' => [
                    'attachment' => [
                        'type' => $type,
                        'payload' => [
                            'attachment_id' => $attachmentId,
                        ],
                    ],
                ],
                'messaging_type' => 'RESPONSE',
                'access_token' => $this->token,
            ];

            $response = Http::post($sendUrl, $payload);

            Log::info('📤 Facebook Send Attachment Message Response', [
                'mime'          => $mime,
                'type'          => $type,
                'upload_result' => $uploadJson,
                'send_response' => $response->json(),
            ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error("⚠️ Facebook sendAttachmentMessage failed: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
