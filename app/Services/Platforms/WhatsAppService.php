<?php

namespace App\Services\Platforms;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhatsAppService
{
    protected string $url;
    protected string $token;
    protected string $phoneNumberId;

    public function __construct()
    {
        // Load WhatsApp settings from database
        $whatsapp = getSystemSettingData('whatsapp', []);

        $this->token = $whatsapp['token'] ?? '';
        $this->phoneNumberId = $whatsapp['phone_number_id'] ?? '';

        // Check if credentials are available
        if (empty($this->token) || empty($this->phoneNumberId)) {
            throw new Exception("WhatsApp credentials are not configured in the database.");
        }

        $this->url = "https://graph.facebook.com/v22.0/{$this->phoneNumberId}/messages";
    }

    public function sendMessage(string $to, string $content, string $type = 'text', ?string $template = null, string $language = 'en_US', array $parameters = []): array
    {
        if ($type === 'template' && $template) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'template',
                'template' => [
                    'name'     => $template,
                    'language' => ['code' => $language],
                    'components' => [
                        [
                            'type'       => 'body',
                            'parameters' => array_map(function ($param) {
                                return ['type' => 'text', 'text' => $param];
                            }, $parameters)
                        ]
                    ]
                ],
            ];
        } else {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => ['body' => $content],
            ];
        }

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

    private function getExtensionFromMime(string $mime): string
    {
        $map = [
            // Image
            'image/jpeg'               => 'jpg',
            'image/png'                => 'png',
            'image/webp'               => 'webp',

            // Video
            'video/mp4'                => 'mp4',

            // Document
            'application/pdf'          => 'pdf',
            'application/msword'       => 'doc',
            'application/vnd.ms-excel' => 'xls',

            // ✅ Add audio MIME types below
            'audio/ogg'                => 'ogg',
            'audio/opus'               => 'ogg',   // WhatsApp voice notes often use Opus inside OGG
            'audio/mpeg'               => 'mp3',
            'audio/mp3'                => 'mp3',
            'audio/mp4'                => 'm4a',
            'audio/x-m4a'              => 'm4a',
            'audio/aac'                => 'aac',
            'audio/wav'                => 'wav',
            'audio/x-wav'              => 'wav',
            'audio/amr'                => 'amr',
        ];

        return $map[$mime] ?? 'bin';
    }

    public function getMediaUrlAndDownload(string $mediaId): ?array
    {
        $accessToken = config('services.whatsapp.token');

        try {
            $meta = Http::withToken($accessToken)->get("https://graph.facebook.com/v18.0/{$mediaId}");
            if ($meta->unauthorized()) {
                Log::error("WhatsApp access token is expired or invalid.");
                return null;
            }

            $url = $meta->json()['url'] ?? null;
            if (!$url) return null;

            $media = Http::withToken($accessToken)->get($url);
            if (!$media->ok()) {
                Log::error("Failed to download media content from WhatsApp for mediaId: {$mediaId}");
                return null;
            }

            $mime      = $media->header('Content-Type');
            $extension = $this->getExtensionFromMime($mime);
            $filename  = "attachments/wa_" . uniqid() . "." . $extension;

            Storage::disk('public')->put($filename, $media->body());

            return [
                'path'      => Storage::url($filename),  // e.g., /storage/attachments/wa_xyz.jpg
                'full_path' => $filename,                // e.g., attachments/wa_xyz.jpg (useful for DB)
                'mime'      => $mime,
                'extension' => $extension,
                'type'      => explode('/', $mime)[0],   // image, video, audio, etc.
            ];
        } catch (\Exception $e) {
            Log::error("WhatsApp media download error: " . $e->getMessage());
            return null;
        }
    }

    public function uploadMedia(string $filePath, string $mimeType): ?string
    {
        $url = "https://graph.facebook.com/v18.0/" . config('services.whatsapp.phone_number_id') . "/media";

        $response = Http::withToken($this->token)
            ->attach(
                'file',
                fopen($filePath, 'r'),            // pass the local path
                basename($filePath)
            )
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'type' => $mimeType,
            ]);

        Log::info('WhatsApp Upload Media Response:', $response->json());

        return $response->successful() ? $response->json('id') : null;
    }

    public function sendMediaMessage(string $to, string $mediaId, string $type = 'image'): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => $type,
            $type               => ['id' => $mediaId],
        ];

        Log::info("Sending WhatsApp media message", $payload);

        $response = Http::withToken($this->token)->post($this->url, $payload);

        Log::info("WhatsApp Media Message Response:", $response->json());

        return $response->json();
    }

    public function sendImageWithCaption(string $to, string $imageUrl, string $caption): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'image',
            'image' => [
                'link'    => $imageUrl,
                'caption' => $caption,
            ],
        ];

        Log::info('WhatsApp Image with Caption Payload:', $payload);

        $response = Http::withToken($this->token)->post($this->url, $payload);

        Log::info('WhatsApp Image with Caption Response:', $response->json());

        return $response->json();
    }

    /**
     * Send an interactive list message (with buttons for user to select an option).
     *
     * @param string $to The phone number of the recipient.
     * @param string $text The main content of the message.
     * @param array $options An array of options (buttons) for the user to choose from.
     * @return array
     */
    public function sendInteractiveMessage(string $to, string $text, array $options): array
    {
        // Create the list message payload
        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => [
                    'text' => $text
                ],
                'action' => [
                    'button' => 'Choose one',
                    'sections' => [
                        [
                            'title' => 'Rate your experience',
                            'rows' => array_map(function ($option) {
                                return [
                                    'id'    => $option['value'], 
                                    'title' => $option['label'],
                                ];
                            }, $options),
                        ],
                    ],
                ],
            ],
        ];

        Log::info('WhatsApp Interactive List Payload:', $payload);

        // Send the request to WhatsApp API
        $response = Http::withToken($this->token)->post($this->url, $payload);
        Log::info('WhatsApp Interactive List Response:', $response->json());

        return $response->json();
    }

}
