<?php

use App\Enums\PlatformTypeWiseWeightage;
use App\Models\Conversation;
use App\Models\MessageTemplate;
use App\Models\SystemSetting;
use Illuminate\Http\UploadedFile;
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


if (!function_exists('storeAndDetectAttachment')) {
    /**
     * Store a file and detect its type.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $disk
     * @param string $folder
     * @return array
     */
    function storeAndDetectAttachment(UploadedFile $file, string $disk = 'public', string $folder = 'uploads/messages'): array
    {
        // Store file (returns relative path, e.g. 'uploads/messages/filename.png')
        $path = $file->store($folder, $disk);

        // File info
        $mime = $file->getClientMimeType();
        $size = $file->getSize();

        // Detect type
        $type = match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'document',
        };

        return [
            'path'       => $path, // ✅ store only relative path (no '/storage/', no domain)
            'local_path' => storage_path("app/{$disk}/{$path}"),
            'type'       => $type,
            'mime'       => $mime,
            'size'       => $size,
        ];
    }
}

if (! function_exists('getFeedbackRatingsFromCustomer')) {
    /**
     * Get numeric rating value for a customer feedback option.
     *
     * Example Mapping:
     * - Excellent => 5
     * - Good      => 4
     * - Average   => 3
     * - Bad       => 2
     * - Very Bad  => 1
     *
     * Works for WhatsApp, Facebook, or other platforms.
     *
     * @param string $label The interactive option label
     * @param string|null $templateType Template type, default 'cchat'
     * @return int|null Numeric rating or null if label not found
     */
    function getFeedbackRatingsFromCustomer(string $label, ?string $templateType = 'cchat'): ?int
    {
        $template = MessageTemplate::where('type', $templateType)->first();

        if (! $template || empty($template->options)) {
            return null;
        }

        foreach ($template->options as $option) {
            if (strcasecmp($option['label'], $label) === 0) {
                return $option['value'];
            }
        }

        return null;
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

if (!function_exists('getSystemSettingData')) {
    /**
     * Get a system setting by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed|null
     */
    function getSystemSettingData(string $key, $default = null)
    {
        // Avoid querying if table doesn't exist (migrations/seeding)
        if (!\Schema::hasTable('system_settings')) {
            return $default;
        }

        $setting = \App\Models\SystemSetting::where('setting_key', $key)->first();
        return $setting ? $setting->setting_value : $default;
    }
}


if (!function_exists('sendToDispatcher')) {
    /**
     * Send payload to dispatcher API safely.
     *
     * @param array $payload
     * @param bool $logErrors Whether to log errors (default: true)
     * @return bool True if sent successfully, false otherwise
     */
    function sendToDispatcher(array $payload, bool $logErrors = true): bool
    {
        // Ensure dispatcher config exists
        if (!config()->has('dispatcher.url') || !config()->has('dispatcher.endpoints.handler')) {
            if ($logErrors) {
                \Log::error('[DISPATCHER] Configuration missing', ['payload' => $payload]);
            }
            return false;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::acceptJson()
                ->post(config('dispatcher.url') . config('dispatcher.endpoints.handler'), $payload);

            if ($response->ok()) {
                \Log::info('[DISPATCHER] Payload sent successfully', ['payload' => $payload]);
                return true;
            }

            if ($logErrors) {
                \Log::error('[DISPATCHER] Failed to send payload', [
                    'payload' => $payload,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }

            return false;
        } catch (\Exception $e) {
            if ($logErrors) {
                \Log::error('[DISPATCHER] Exception while sending payload', [
                    'payload' => $payload,
                    'exception' => $e->getMessage(),
                ]);
            }
            return false;
        }
    }
}


if (! function_exists('getRandomSocialPageConversation')) {
    /**
     * Get a random social page conversation (mock).
     *
     * @return array|null
     */
    function getRandomSocialPageConversation(): ?array
    {
        $conversations = [
            [
                "id" => 1,
                "post_id" => 101,
                "platform" => "facebook",
                "trace_id" => "C-101a",
                "customer" => [
                    "id" => 1,
                    "name" => "Rumana Begum",
                    "email" => null,
                    "phone" => "",
                    "type" => "customer",
                    "profile_photo" => null
                ],
                "type" => "comment",
                "info" => "Rumana commented on post 101: 'Great workshop, learned a lot!'",
                "started_at" => "2025-12-18T01:28:09.000000Z",
                "end_at" => null,
                "wrap_up_info" => null,
                "is_ended" => false
            ],
            [
                "id" => 2,
                "post_id" => 101,
                "platform" => "facebook",
                "trace_id" => "C-101b",
                "customer" => [
                    "id" => 2,
                    "name" => "Tarek Hassan",
                    "email" => null,
                    "phone" => "",
                    "type" => "customer",
                    "profile_photo" => null
                ],
                "type" => "reply",
                "info" => "Tarek replied on post 101: 'Totally agree, it was very insightful!'",
                "started_at" => "2025-12-18T02:15:09.000000Z",
                "end_at" => null,
                "wrap_up_info" => null,
                "is_ended" => false
            ],
            [
                "id" => 3,
                "post_id" => 102,
                "platform" => "facebook",
                "trace_id" => "C-102a",
                "customer" => [
                    "id" => 3,
                    "name" => "Rafi Ahmed",
                    "email" => null,
                    "phone" => "",
                    "type" => "customer",
                    "profile_photo" => null
                ],
                "type" => "comment",
                "info" => "Rafi commented on post 102: 'Congratulations! Great initiative.'",
                "started_at" => "2025-12-18T03:05:00.000000Z",
                "end_at" => null,
                "wrap_up_info" => null,
                "is_ended" => false
            ],
            [
                "id" => 4,
                "post_id" => 103,
                "platform" => "facebook",
                "trace_id" => "C-103a",
                "customer" => [
                    "id" => 4,
                    "name" => "Nusrat Jahan",
                    "email" => null,
                    "phone" => "",
                    "type" => "customer",
                    "profile_photo" => null
                ],
                "type" => "reply",
                "info" => "Nusrat replied on post 103: 'Loved the practical approach, very helpful!'",
                "started_at" => "2025-12-18T04:10:00.000000Z",
                "end_at" => null,
                "wrap_up_info" => null,
                "is_ended" => false
            ],
            [
                "id" => 5,
                "post_id" => 104,
                "platform" => "facebook",
                "trace_id" => "C-104a",
                "customer" => [
                    "id" => 5,
                    "name" => "Hasan Mahmud",
                    "email" => null,
                    "phone" => "",
                    "type" => "customer",
                    "profile_photo" => null
                ],
                "type" => "comment",
                "info" => "Hasan commented on post 104: 'Next workshop will be even better!'",
                "started_at" => "2025-12-18T04:15:00.000000Z",
                "end_at" => null,
                "wrap_up_info" => null,
                "is_ended" => false
            ],
        ];

        if (empty($conversations)) {
            return null;
        }

        return $conversations[array_rand($conversations)];
    }
}
