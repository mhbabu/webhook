<?php
namespace App\Services\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Services\SocialSyncService;

/**
 * LinkedInAdapter
 *
 * Uses LinkedIn Marketing / UGC & Social Actions APIs:
 * - Fetch org posts (GET /ugcPosts?q=authors&authors=urn:li:organization:{orgId})
 * - For each post, obtain the activity URN / id to fetch comments & reactions via socialActions
 *
 * Pagination: offset-based via `start` and `count` parameters.
 *
 * References:
 * - LinkedIn Posts (UGC / Shares) and Comments/Reactions APIs.
 */
class LinkedInAdapter
{
    protected SocialSyncService $syncService;
    protected string $token;
    protected int $defaultCount = 50;

    public function __construct(SocialSyncService $syncService)
    {
        $this->syncService = $syncService;
        $this->token = config('services.linkedin.client_token') ?? null;
        if (!$this->token) {
            // try reading from credentials in PlatformAccount
            // keep flexible: account.credentials['access_token'] is used in calls below if present
        }
    }

    /**
     * Sync organization posts (UGC Posts) using offset pagination.
     *
     * LinkedIn UGC endpoint example:
     * GET https://api.linkedin.com/v2/ugcPosts?q=authors&authors=urn:li:organization:{orgId}&start=0&count=50
     */
    public function syncOrgPosts(Platform $platform, PlatformAccount $account, int $count = null)
    {
        $count = $count ?? $this->defaultCount;
        $orgUrn = 'urn:li:organization:' . $account->platform_account_id;

        $accessToken = $this->resolveAccessToken($account);

        $start = 0;
        $fetched = 0;

        do {
            $url = "https://api.linkedin.com/v2/ugcPosts";
            $params = [
                'q' => 'authors',
                'authors' => $orgUrn,
                'start' => $start,
                'count' => $count,
            ];

            Log::info("[LinkedInAdapter] Fetching posts start={$start} count={$count} for org {$orgUrn}");
            $res = Http::withToken($accessToken)
                ->acceptJson()
                ->get($url, $params);

            if (!$res->successful()) {
                $this->syncService->logSync($platform, $account, 'posts', 'failed', $res->body());
                Log::error("[LinkedInAdapter] posts fetch failed: " . $res->body());
                return;
            }

            $json = $res->json();
            $data = $json['elements'] ?? [];

            foreach ($data as $el) {
                // Normalization: platform_post_id -> id or urn
                $platformPostId = $el['id'] ?? ($el['ugcPost'] ?? null) ?? null;
                // LinkedIn often uses 'id' or a combination; also we can use 'activity' urn for socialActions
                $activityUrn = $el['id'] ?? ($el['activity'] ?? null) ?? null;

                $caption = $this->extractLinkedInCaption($el);

                $payload = [
                    'platform_post_id' => $platformPostId ?? $activityUrn,
                    'caption' => $caption,
                    'type' => 'linkedin',
                    'posted_at' => $el['created']['time'] ? date('c', intval($el['created']['time'] / 1000)) : null,
                    'raw' => $el,
                    'media' => $this->extractLinkedInMedia($el),
                ];

                $post = $this->syncService->upsertPost($platform, $account, $payload);

                // LinkedIn comments & reactions use the socialActions API with the activity URN.
                // Get activity URN: some UGC payloads include "id" or "activity", or you can derive:
                // activityUrn = 'urn:li:activity:{activityId}'
                $activityUrn = $el['activity'] ?? $el['id'] ?? null;
                if ($activityUrn) {
                    $this->syncCommentsForActivity($platform, $account, $post, $activityUrn);
                    $this->syncReactionsForActivity($platform, $account, $post, $activityUrn);
                } else {
                    // Sometimes UGC posts provide share content with "id" and you need to resolve socialAction URN
                    // You may need an extra lookup here. For many org posts 'id' works.
                }
            }

            $fetched = count($data);
            $start += $fetched;
        } while ($fetched === $count && $fetched > 0);

        $this->syncService->logSync($platform, $account, 'posts', 'success', null);
    }

    /**
     * Extract caption/text from UGC post payload safely.
     */
    protected function extractLinkedInCaption(array $el): ?string
    {
        // UGC posts usually store text in specific fields. Try common shapes:
        if (!empty($el['specificContent']['com.linkedin.ugc.ShareContent']['shareCommentary']['text'])) {
            return $el['specificContent']['com.linkedin.ugc.ShareContent']['shareCommentary']['text'];
        }
        if (!empty($el['specificContent']['com.linkedin.ugc.ShareContent']['media'])) {
            // maybe a media post; also try elements text
        }
        return null;
    }

    /**
     * Extract media array from LinkedIn UGC post representation
     */
    protected function extractLinkedInMedia(array $el): array
    {
        $media = [];
        $sc = $el['specificContent']['com.linkedin.ugc.ShareContent'] ?? null;
        if ($sc && !empty($sc['media'])) {
            foreach ($sc['media'] as $m) {
                $media[] = [
                    'type' => $m['mediaType'] ?? 'image',
                    'url' => $m['media']?['url'] ?? null,
                    'thumbnail' => $m['media']?['thumbnail'] ?? null,
                ];
            }
        }
        return $media;
    }

    /**
     * Sync comments for a LinkedIn activity (socialAction)
     *
     * Endpoint: GET https://api.linkedin.com/v2/socialActions/{activityUrn}/comments?start=0&count=50
     */
    public function syncCommentsForActivity(Platform $platform, PlatformAccount $account, $post, string $activityUrn, int $count = null)
    {
        $count = $count ?? $this->defaultCount;
        $accessToken = $this->resolveAccessToken($account);

        $start = 0;
        do {
            $url = "https://api.linkedin.com/v2/socialActions/{$activityUrn}/comments";
            $params = ['start' => $start, 'count' => $count];

            $res = Http::withToken($accessToken)->acceptJson()->get($url, $params);
            if (!$res->successful()) {
                Log::error("[LinkedInAdapter] comments fetch failed: " . $res->body());
                return;
            }

            $json = $res->json();
            $elements = $json['elements'] ?? [];

            foreach ($elements as $c) {
                // LinkedIn comment shape:
                // { id, created, actor, message }
                $payload = [
                    'platform_comment_id' => $c['id'] ?? null,
                    'platform_parent_id' => $c['parent'] ?? null,
                    'author_platform_id' => $c['actor'] ?? null,
                    'author_name' => $this->extractActorName($c['actorDetails'] ?? null) ?? null,
                    'message' => $c['message'] ?? ($c['message']['text'] ?? null),
                    'commented_at' => isset($c['created']['time']) ? date('c', intval($c['created']['time'] / 1000)) : null,
                    'raw' => $c,
                ];

                $comment = $this->syncService->upsertComment($post, $payload);

                // You may also want to fetch reactions to each comment (LinkedIn supports comment reactions).
                // LinkedIn reaction endpoint for comments: socialActions/{commentUrn}/likes or reactions endpoint.
            }

            $fetched = count($elements);
            $start += $fetched;
        } while ($fetched === $count && $fetched > 0);

        $this->syncService->logSync($platform, $account, 'comments', 'success', null);
    }

    /**
     * Sync reactions for the activity (post)
     *
     * Endpoint example (Reactions API): GET https://api.linkedin.com/v2/reactions/(some shape) or socialActions/{activityUrn}/likes
     * See LinkedIn Reactions API docs — shapes may vary by permission.
     */
    public function syncReactionsForActivity(Platform $platform, PlatformAccount $account, $post, string $activityUrn, int $count = null)
    {
        $count = $count ?? $this->defaultCount;
        $accessToken = $this->resolveAccessToken($account);
        $start = 0;

        do {
            $url = "https://api.linkedin.com/v2/socialActions/{$activityUrn}/likes";
            $params = ['start' => $start, 'count' => $count];

            $res = Http::withToken($accessToken)->acceptJson()->get($url, $params);
            if (!$res->successful()) {
                Log::error("[LinkedInAdapter] reactions fetch failed: " . $res->body());
                return;
            }

            $json = $res->json();
            $elements = $json['elements'] ?? [];

            foreach ($elements as $r) {
                // r usually contains actor (urn), reactionType, created time
                $payload = [
                    'platform_reaction_id' => $r['id'] ?? null,
                    'user_platform_id' => $r['actor'] ?? null,
                    'reaction_type' => $r['reactionType'] ?? null,
                    'reacted_at' => isset($r['created']['time']) ? date('c', intval($r['created']['time'] / 1000)) : null,
                    'raw' => $r,
                ];
                $this->syncService->upsertReaction($post, null, $payload);
            }

            $fetched = count($elements);
            $start += $fetched;
        } while ($fetched === $count && $fetched > 0);

        $this->syncService->logSync($platform, $account, 'reactions', 'success', null);
    }

    /**
     * Resolve access token for account: either global or stored per account in credentials
     */
    protected function resolveAccessToken(PlatformAccount $account): string
    {
        $cred = $account->credentials ?? null;
        if (!empty($cred['access_token'])) {
            return $cred['access_token'];
        }
        // fallback to config token if present
        return config('services.linkedin.access_token') ?? '';
    }

    protected function extractActorName($actorDetails): ?string
    {
        // actorDetails may not be present. LinkedIn returns actor URNs like 'urn:li:person:xxxxx'
        // If you have a mapping or can fetch profile, do that. For now return null or URN.
        if (is_string($actorDetails)) return $actorDetails;
        if (is_array($actorDetails) && !empty($actorDetails['localizedFirstName'])) {
            return trim(($actorDetails['localizedFirstName'] ?? '') . ' ' . ($actorDetails['localizedLastName'] ?? ''));
        }
        return null;
    }
}
