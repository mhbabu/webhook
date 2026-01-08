<?php

namespace App\Http\Resources\SocialPage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'content'         => $this->content,
            'create_at'       => $this->posted_at,
            'location'        => $this->location,
            'permalink_url'   => $this->permalink_url,
            // 'post_type'       => $this->post_type,
            'tags'            => $this->tags,
            'attachments'     => $this->formatAttachments($this->attachment)
        ];
    }

    /**
     * Normalize Facebook attachments dynamically
     */
    protected function formatAttachments($attachments): array
    {
        if (!is_array($attachments)) {
            return [];
        }

        $results = [];

        foreach ($attachments as $attachment) {

            // 🔹 Album (multiple photos)
            if (!empty($attachment['subattachments']['data'])) {
                foreach ($attachment['subattachments']['data'] as $sub) {
                    if ($mapped = $this->mapAttachment($sub)) {
                        $results[] = $mapped;
                    }
                }
                continue;
            }

            // 🔹 Single attachment
            if ($mapped = $this->mapAttachment($attachment)) {
                $results[] = $mapped;
            }
        }

        return $results;
    }

    /**
     * Map a single Facebook attachment safely
     */
    protected function mapAttachment(array $item): ?array
    {
        // 🖼 IMAGE
        if (!empty($item['media']['image'])) {
            return [
                'type'   => 'image',
                'url'    => $item['media']['image']['src'] ?? null,
                'width'  => $item['media']['image']['width'] ?? null,
                'height' => $item['media']['image']['height'] ?? null,
            ];
        }

        // 🎥 VIDEO
        if (!empty($item['media']['source'])) {
            return [
                'type'   => 'video',
                'url'    => $item['media']['source'],
                'width'  => $item['media']['width'] ?? null,
                'height' => $item['media']['height'] ?? null,
            ];
        }

        // 🔗 LINK
        if (!empty($item['url'])) {
            return [
                'type'   => 'link',
                'url'    => $item['url'],
                'width'  => null,
                'height' => null,
            ];
        }

        return null;
    }
}
