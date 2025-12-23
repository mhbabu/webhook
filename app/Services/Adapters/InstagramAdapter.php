<?php

namespace App\Services\Adapters;

use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Services\SocialSyncService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * InstagramAdapter
 *
 * Uses Instagram Graph API (Business/Creator accounts).
 * - Fetch media (GET /{ig-user-id}/media?fields=...)
 * - For each media: fetch children (if carousel) and comments (/media-id/comments)
 *
 * Pagination: cursor-based via paging.cursors.after or paging.next (Graph API).
 *
 * References:
 * - Instagram Media & Media edges (Instagram Graph API). See docs for fields & pagination.
 */
class InstagramAdapter
{
    protected SocialSyncService $syncService;

    protected string $token;

    public function __construct(SocialSyncService $syncService)
    {
        $this->syncService = $syncService;
        $this->token = config('services.instagram.ig_page_token') ?? config('services.facebook.token');
    }

    /**
     * Sync media (posts) for an IG business account
     *
     * $account->platform_account_id must be the IG User ID (or IG Business account ID)
     *
     * Pagination: cursor-based. We follow `paging.cursors.after` and `paging.next`.
     */
    public function syncMedia(Platform $platform, PlatformAccount $account, int $limit = 50)
    {
        $igUserId = $account->platform_account_id;
        Log::info("[InstagramAdapter] Starting media sync for IG User ID: {$igUserId}");
        // Log::debug("[IG] page={$page}, account={$igUserId}");
        $endpoint = "https://graph.facebook.com/v24.0/{$igUserId}/media";
        // requested fields include children for carousel, and media fields.
        $fields = implode(',', [
            'id',
            'caption',
            'media_type',
            'media_url',
            'thumbnail_url',
            'timestamp',
            'children{ id, media_type, media_url, thumbnail_url }',
        ]);

        $params = [
            'fields' => $fields,
            'limit' => $limit,
            'access_token' => $this->token,
        ];

        $next = $endpoint.'?'.http_build_query($params);
        $page = 1;

        while ($next) {
            Log::info("[InstagramAdapter] Fetching IG media page {$page} for account {$igUserId}");
            $res = Http::get($next);

            if (! $res->successful()) {
                $this->syncService->logSync($platform, $account, 'media', 'failed', $res->body());
                Log::error('[InstagramAdapter] media fetch failed: '.$res->body());

                return;
            }

            $json = $res->json();
            foreach ($json['data'] ?? [] as $media) {
                // Normalize payload expected by SocialSyncService->upsertPost()
                $payload = [
                    'platform_post_id' => $media['id'],
                    'caption' => $media['caption'] ?? null,
                    'type' => $media['media_type'] ?? null,
                    'posted_at' => $media['timestamp'] ?? null,
                    'raw' => $media,
                    'media' => $this->normalizeMediaArray($media),
                ];

                $post = $this->syncService->upsertPost($platform, $account, $payload);

                // 🔥 Sync post reactions (likes)
                // $this->syncPostReactions($platform, $account, $post, $media['id']);

                // Sync comments
                $this->syncCommentsForMedia($platform, $account, $post, $media['id']);

                // Note: reactions (likes) endpoint for IG media is /{media-id}/insights or /{media-id}/likes
                // Instagram Graph supports /{media-id}/likes if permissions present; you can implement similarly.
            }

            // Pagination: prefer cursor-based 'paging.cursors.after' or 'paging.next'
            $next = null;
            if (! empty($json['paging']['next'])) {
                $next = $json['paging']['next'];
            } elseif (! empty($json['paging']['cursors']['after'])) {
                // Build next URL with `after` param (safer to use the 'next' URL, but just in case)
                $after = $json['paging']['cursors']['after'];
                $params['after'] = $after;
                $next = $endpoint.'?'.http_build_query($params);
            }
            $page++;
        }

        $this->syncService->logSync($platform, $account, 'media', 'success', null);
    }

    /**
     * Normalize attachments / children for a single media object
     */
    protected function normalizeMediaArray(array $media): array
    {
        $result = [];

        // Primary media
        $primaryUrl = $this->downloadAndStoreInstagramMedia(
            $media['media_url'] ?? $media['thumbnail_url'] ?? '',
            $media['media_type'] ?? 'IMAGE'
        );

        $result[] = [
            'type' => $media['media_type'] ?? null,
            'url' => $primaryUrl,
            'thumbnail' => $media['thumbnail_url'] ?? null,
        ];

        // Carousel children
        if (! empty($media['children']['data'])) {
            foreach ($media['children']['data'] as $child) {
                $childUrl = $this->downloadAndStoreInstagramMedia(
                    $child['media_url'] ?? '',
                    $child['media_type'] ?? 'IMAGE'
                );

                $result[] = [
                    'type' => $child['media_type'] ?? null,
                    'url' => $childUrl,
                    'thumbnail' => $child['thumbnail_url'] ?? null,
                ];
            }
        }

        return $result;
    }

    /**
     * Sync comments for a media (mediaId)
     *
     * Pagination via cursor-based 'after' / 'paging.next'
     */
    public function syncCommentsForMedia(Platform $platform, PlatformAccount $account, $post, string $mediaId, int $limit = 50)
    {
        $endpoint = "https://graph.facebook.com/v24.0/{$mediaId}/comments";
        $fields = 'id,text,username,timestamp,parent';
        $params = [
            'fields' => $fields,
            'limit' => $limit,
            'access_token' => $this->token,
        ];

        $next = $endpoint.'?'.http_build_query($params);

        while ($next) {
            $res = Http::retry(3, 500)->get($next);
            if (! $res->successful()) {
                $this->syncService->logSync($platform, $account, 'comments', 'failed', $res->body());
                Log::error('[InstagramAdapter] comments fetch failed: '.$res->body());

                return;
            }
            $json = $res->json();

            foreach ($json['data'] ?? [] as $c) {
                $payload = [
                    'platform_comment_id' => $c['id'],
                    'platform_parent_id' => $c['parent']['id'] ?? null, // IG comment replies use 'parent'
                    'author_platform_id' => null, // Instagram Basic returns username only; the ID may be unavailable
                    'author_name' => $c['username'] ?? null,
                    'message' => $c['text'] ?? null,
                    'commented_at' => $c['timestamp'] ?? null,
                    'raw' => $c,
                ];

                $comment = $this->syncService->upsertComment($post, $payload);

                // Instagram does not provide per-comment reactions via Graph API in many cases;
                // if reactions are available, implement similar to Facebook.
            }

            // pagination
            $next = $json['paging']['next'] ?? null;
        }

        $this->syncService->logSync($platform, $account, 'comments', 'success', null);
    }

    public function syncPostReactions(
        Platform $platform,
        PlatformAccount $account,
        $post,
        string $mediaId
    ) {
        $endpoint = "https://graph.facebook.com/v24.0/{$mediaId}";
        $params = [
            'fields' => 'like_count',
            'access_token' => $this->token,
        ];

        $res = Http::retry(3, 500)->get($endpoint, $params);

        if (! $res->successful()) {
            Log::error('[InstagramAdapter] post reaction fetch failed', [
                'media_id' => $mediaId,
                'error' => $res->body(),
            ]);

            return;
        }

        $data = $res->json();

        if (! isset($data['like_count'])) {
            return;
        }

        // Store aggregated reaction
        $this->syncService->upsertReactionAggregate(
            post: $post,
            reactionType: 'like',
            count: (int) $data['like_count'],
            raw: $data
        );
    }

    protected function downloadAndStoreInstagramMedia(
        string $remoteUrl,
        string $mediaType = 'IMAGE'
    ): ?string {
        try {
            if (! $remoteUrl) {
                return null;
            }

            // Detect extension safely
            $extension = $this->detectExtensionFromUrl($remoteUrl, $mediaType);

            // Folder by type
            $folder = match (strtoupper($mediaType)) {
                'VIDEO' => 'instagram/videos',
                default => 'instagram/images',
            };

            $fileName = 'ig_'.Str::uuid().'.'.$extension;
            $filePath = storage_path("app/public/{$folder}/{$fileName}");

            // Ensure directory exists
            if (! is_dir(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }

            // Use stream context (important for IG CDN)
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Mozilla/5.0\r\n",
                    'timeout' => 20,
                ],
            ]);

            $content = file_get_contents($remoteUrl, false, $context);

            if ($content === false) {
                throw new \Exception('Empty media content');
            }

            file_put_contents($filePath, $content);

            // Public URL (Laravel storage:link)
            return "storage/{$folder}/{$fileName}";
        } catch (\Throwable $e) {
            Log::error('Instagram media download failed', [
                'url' => $remoteUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function detectExtensionFromUrl(string $url, string $mediaType): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $ext = pathinfo($path, PATHINFO_EXTENSION);

        $validExtensions = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'webm'];

        if (in_array(strtolower($ext), $validExtensions)) {
            return strtolower($ext);
        }

        // Fallback based on media type
        return match (strtoupper($mediaType)) {
            'VIDEO' => 'mp4',
            default => 'jpg',
        };
    }
}
