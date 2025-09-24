<?php

namespace App\Services\Platforms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $url;
    protected string $token;

    public function __construct()
    {
        $this->url = config('services.whatsapp.url');
        $this->token = config('services.whatsapp.token');
    }

    public function sendMessage(
        string $to,
        string $content,
        string $type = 'text',
        ?string $template = null,
        string $language = 'en_US',
        array $parameters = []
    ): array {
        if ($type === 'template' && $template) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => $language],
                    'components' => [
                        [
                            'type' => 'body',
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

        $response = Http::withToken($this->token)
            ->post($this->url, $payload);

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
}
