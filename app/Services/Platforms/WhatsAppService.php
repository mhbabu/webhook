<?php

namespace App\Services\Platforms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class WhatsAppService
{
    protected string $token;
    protected string $phoneNumberId;
    protected string $url;

    public function __construct()
    {
        // Load WhatsApp settings from DB if available, otherwise use env
        $settings = [];
        if (Schema::hasTable('system_settings') && function_exists('getSystemSettingData')) {
            $settings = getSystemSettingData('whatsapp', []);
        }

        $this->token = $settings['token'] ?? env('WHATSAPP_ACCESS_TOKEN', '');
        $this->phoneNumberId = $settings['phone_number_id'] ?? env('WHATSAPP_PHONE_NUMBER_ID', '');
        $this->url = "https://graph.facebook.com/v22.0/{$this->phoneNumberId}/messages";

        if (empty($this->token) || empty($this->phoneNumberId)) {
            Log::warning("WhatsApp credentials are not fully configured.");
        }
    }

    // --- Send text or template message ---
    public function sendMessage(string $to, string $content, string $type = 'text', ?string $template = null, string $language = 'en_US', array $parameters = []): array
    {
        $payload = $type === 'template' && $template
            ? [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'template',
                'template' => [
                    'name'     => $template,
                    'language' => ['code' => $language],
                    'components' => [
                        [
                            'type'       => 'body',
                            'parameters' => array_map(fn($param) => ['type' => 'text', 'text' => $param], $parameters)
                        ]
                    ]
                ],
            ]
            : [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => ['body' => $content],
            ];

        Log::info('WhatsApp Payload:', $payload);

        $response = Http::withToken($this->token)->post($this->url, $payload);

        Log::info('WhatsApp Response:', $response->json());

        return $response->json();
    }

    public function sendTextMessage(string $to, string $message): array
    {
        return $this->sendMessage($to, $message, 'text');
    }

    public function sendTemplateMessage(string $to, string $template, array $parameters = [], string $language = 'en_US'): array
    {
        return $this->sendMessage($to, '', 'template', $template, $language, $parameters);
    }

    // --- Media handling ---
    private function getExtensionFromMime(string $mime): string
    {
        $map = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'application/pdf' => 'pdf', 'application/msword' => 'doc', 'application/vnd.ms-excel' => 'xls',
            'audio/ogg' => 'ogg', 'audio/opus' => 'ogg', 'audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3',
            'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/aac' => 'aac', 'audio/wav' => 'wav',
            'audio/x-wav' => 'wav', 'audio/amr' => 'amr'
        ];

        return $map[$mime] ?? 'bin';
    }

    public function getMediaUrlAndDownload(string $mediaId): ?array
    {
        try {
            $meta = Http::withToken($this->token)->get("https://graph.facebook.com/v18.0/{$mediaId}");
            if ($meta->unauthorized()) {
                Log::error("WhatsApp access token invalid.");
                return null;
            }

            $url = $meta->json('url');
            if (!$url) return null;

            $media = Http::withToken($this->token)->get($url);
            if (!$media->ok()) return null;

            $mime = $media->header('Content-Type');
            $ext = $this->getExtensionFromMime($mime);
            $filename = "attachments/wa_" . uniqid() . "." . $ext;

            Storage::disk('public')->put($filename, $media->body());

            return [
                'path' => Storage::url($filename),
                'full_path' => $filename,
                'mime' => $mime,
                'extension' => $ext,
                'type' => explode('/', $mime)[0],
            ];
        } catch (\Exception $e) {
            Log::error("WhatsApp media download error: " . $e->getMessage());
            return null;
        }
    }

    public function uploadMedia(string $filePath, string $mimeType): ?string
    {
        $url = "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/media";

        $response = Http::withToken($this->token)
            ->attach('file', fopen($filePath, 'r'), basename($filePath))
            ->post($url, ['messaging_product' => 'whatsapp', 'type' => $mimeType]);

        return $response->successful() ? $response->json('id') : null;
    }

    public function sendMediaMessage(string $to, string $mediaId, string $type = 'image'): array
    {
        $payload = ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => $type, $type => ['id' => $mediaId]];

        $response = Http::withToken($this->token)->post($this->url, $payload);
        return $response->json();
    }

    public function sendImageWithCaption(string $to, string $imageUrl, string $caption): array
    {
        $payload = ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'image', 'image' => ['link' => $imageUrl, 'caption' => $caption]];
        $response = Http::withToken($this->token)->post($this->url, $payload);
        return $response->json();
    }

    // --- Interactive list message ---
    public function sendInteractiveMessage(string $to, string $text, array $options): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => $text],
                'action' => [
                    'button' => 'Choose one',
                    'sections' => [['title' => 'Rate your experience', 'rows' => array_map(fn($o) => ['id' => $o['value'], 'title' => $o['label']], $options)]],
                ],
            ],
        ];

        $response = Http::withToken($this->token)->post($this->url, $payload);
        return $response->json();
    }
}
