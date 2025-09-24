<?php

namespace App\Services\Platforms;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    protected string $url;
    protected string $token;

    public function __construct()
    {
        $this->url   = config('services.whatsapp.url');
        $this->token = config('services.whatsapp.token');
    }

    /**
     * Send a WhatsApp message (text or template)
     *
     * @param string $to Recipient phone number
     * @param string $content Message content (used for text)
     * @param string $type Type of message: 'text' or 'template'
     * @param ?string $template Template name (required if type is 'template')
     * @param string $language Language code for template (default: en_US)
     * @return array API response as array
     */
    public function sendMessage(
        string $to,
        string $content,
        string $type = 'text',
        ?string $template = null,
        string $language = 'en_US'
    ): array {
        if ($type === 'template' && $template) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to'   => $to,
                'type' => 'template',
                'template' => [
                    'name'     => $template,
                    'language' => ['code' => $language],
                ],
            ];
        } else {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to'   => $to,
                'type' => 'text',
                'text' => ['body' => $content],
            ];
        }

        $response = Http::withToken($this->token)
            ->post($this->url, $payload);

        return $response->json();
    }

    /**
     * Shortcut to send a text message
     */
    public function sendTextMessage(string $to, string $message): array
    {
        return $this->sendMessage($to, $message, 'text');
    }

    /**
     * Shortcut to send a template message
     */
    public function sendTemplateMessage(string $to, string $template, string $language = 'en_US'): array
    {
        return $this->sendMessage($to, '', 'template', $template, $language);
    }
}
