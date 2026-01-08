<?php

namespace App\Http\Resources\SocialPage;

use Illuminate\Http\Resources\Json\JsonResource;

class PostCommentReplyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'content'      => $this->content,
            'created_at'   => $this->replied_at,
            'from'         => $this->customer ? ['id'   => $this->customer->id, 'name' => $this->customer->name] : null,
            // ✅ Format reply attachments dynamically
            'attachments'  => $this->formatAttachments($this->attachment),
        ];
    }

    /**
     * Normalize attachments dynamically
     */
    protected function formatAttachments($attachments): array
    {
        if (!is_array($attachments)) {
            return [];
        }

        $results = [];

        foreach ($attachments as $attachment) {

            // Album / multiple attachments
            if (!empty($attachment['subattachments']['data'])) {
                foreach ($attachment['subattachments']['data'] as $sub) {
                    if ($mapped = $this->mapAttachment($sub)) {
                        $results[] = $mapped;
                    }
                }
                continue;
            }

            // Single attachment
            if ($mapped = $this->mapAttachment($attachment)) {
                $results[] = $mapped;
            }
        }

        return $results;
    }

    /**
     * Map a single attachment safely
     */
    protected function mapAttachment(array $item): ?array
    {
        // 🖼 IMAGE
        if (!empty($item['media']['image'])) {
            return [
                'type'   => $item['type'] ?? 'image',
                'url'    => $item['media']['image']['src'] ?? null,
                'width'  => $item['media']['image']['width'] ?? null,
                'height' => $item['media']['image']['height'] ?? null,
            ];
        }

        // 🎥 VIDEO
        if (!empty($item['media']['source'])) {
            return [
                'type'   => $item['type'] ?? 'video',
                'url'    => $item['media']['source'],
                'width'  => $item['media']['width'] ?? null,
                'height' => $item['media']['height'] ?? null,
            ];
        }

        // 🔗 LINK
        if (!empty($item['url'])) {
            return [
                'type'   => $item['type'] ?? 'link',
                'url'    => $item['url'],
                'width'  => null,
                'height' => null,
            ];
        }

        return null;
    }
}

