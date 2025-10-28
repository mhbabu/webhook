<?php

use App\Enums\PlatformTypeWiseWeightage;
use App\Models\Conversation;
use Illuminate\Support\Facades\Redis;

/**
 * Return a standardized JSON response.
 *
 * @param string $message
 * @param bool $status
 * @param mixed|null $data
 * @param int $statusCode
 * @return \Illuminate\Http\JsonResponse
 */
if (!function_exists('jsonResponse')) {
    function jsonResponse(string $message, bool $status, $data = null, int $statusCode = 200)
    {
        return response()->json([
            'status'  => $status,
            'message' => $message,
            'data'    => $data
        ], $statusCode);
    }
}

/**
 * Return a standardized JSON response with pagination data.
 *
 * @param string $message
 * @param bool $status
 * @param array $response
 * @param int $statusCode
 * @return \Illuminate\Http\JsonResponse
 */
if (!function_exists('jsonResponseWithPagination')) {
    function jsonResponseWithPagination(string $message, bool $status, array $response, int $statusCode = 200)
    {
        return response()->json(['message' => $message, 'status' => $status] + $response, $statusCode);
    }
}

/**
 * Check if the current role is authorized to create the target role.
 *
 * @param string $currentRole
 * @param string $targetRole
 * @return bool
 */
if (!function_exists('isRoleCreationAuthorized')) {
    function isRoleCreationAuthorized(string $currentRole, string $targetRole): bool
    {
        $roleHierarchy = [
            'Super Admin' => ['Admin', 'Supervisor', 'Agent'],
            'Admin'       => ['Supervisor', 'Agent'],
            'Supervisor'  => ['Agent'],
            'Agent'       => [],
        ];

        return in_array($targetRole, $roleHierarchy[$currentRole] ?? []);
    }
}

/**
 * Get the weight of a given platform.
 *
 * Accepts a string (platform name), a PlatformTypeWiseWeightage enum instance, or null.
 * Returns the numeric weight for conversation limits/capacity.
 *
 * @param string|PlatformTypeWiseWeightage|null $platform
 * @return int
 */
if (!function_exists('getPlatformWeight')) {
    function getPlatformWeight(string|PlatformTypeWiseWeightage|null $platform): int
    {
        if ($platform instanceof PlatformTypeWiseWeightage) {
            return $platform->weight();
        }

        $normalized = strtolower(trim($platform));
        $enum = PlatformTypeWiseWeightage::tryFrom($normalized);

        return $enum->weight();
    }
}

/**
 * Get the count of active conversations for a specific agent.
 *
 * Active conversations = not ended yet OR ended within the configured expiration time.
 *
 * @param int $agentId
 * @return int
 */
if (!function_exists('getAgentActiveConversationsCount')) {
    function getAgentActiveConversationsCount(int $agentId): int
    {
        return Conversation::where('agent_id', $agentId)
            ->where(function ($query) {
                $query->whereNull('end_at')
                      ->orWhere('end_at', '>=', now()->subHours(config('services.conversation.conversation_expire_hours')));
            })
            ->count();
    }
}

/**
 * Update or insert agent (user) data in Redis.
 *
 * Responsibilities:
 * - Updates agent info under "agent:{id}" hash in Redis.
 * - Removes the ended platform from CONTACT_TYPE if the agent
 *   had exactly one active conversation on that platform.
 * - Deletes the ended conversation key ("conversation:{id}") from Redis.
 *
 * @param \App\Models\User $user
 * @param \App\Models\Conversation $conversation
 * @return void
 */
if (!function_exists('updateUserInRedis')) {
    function updateUserInRedis($user, $conversation): void
    {
        $endedPlatform       = $conversation->platform;
        $hashKey             = "agent:{$user->id}";
        $removedConversation = "conversation:{$conversation->id}";

        // Fetch CONTACT_TYPE from Redis
        $contactTypesJson = Redis::hGet($hashKey, 'CONTACT_TYPE') ?? '[]';
        $contactTypes = json_decode($contactTypesJson, true) ?: [];

        // Count active conversations for this agent + platform
        $activeConversations = Conversation::where('agent_id', $user->id)
            ->where('platform', $endedPlatform)
            ->where(function ($query) {
                $query->whereNull('end_at')
                      ->orWhere('end_at', '>=', now()->subHours(config('services.conversation.conversation_expire_hours')));
            })
            ->count();

        // Remove platform only if agent had exactly one active conversation
        if ($endedPlatform && $activeConversations === 1 && in_array($endedPlatform, $contactTypes)) {
            $contactTypes = array_values(array_filter($contactTypes, fn($p) => $p !== $endedPlatform));
        }

        // Increment agent's current_limit by platform weight
        $weight = getPlatformWeight($endedPlatform ?? null);
        $user->increment('current_limit', $weight);

        // Prepare agent data for Redis
        $agentData = [
            "AGENT_ID"        => $user->id,
            "AGENT_TYPE"      => 'NORMAL',
            "STATUS"          => $user->current_status,
            "MAX_SCOPE"       => $user->max_limit,
            "AVAILABLE_SCOPE" => $user->current_limit,
            "CONTACT_TYPE"    => json_encode($contactTypes),
            "SKILL"           => json_encode(
                $user->platforms()
                    ->pluck('name')
                    ->map(fn($n) => strtolower($n))
                    ->toArray()
            ),
            "BUSYSINCE"       => now()->format('Y-m-d H:i:s') ?? '',
        ];

        // Save to Redis and remove ended conversation key
        Redis::hMSet($hashKey, $agentData);
        Redis::del($removedConversation);
    }
}

