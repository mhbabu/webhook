<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;

class DashboardController extends Controller
{
    private const AGENT_HASH_PREFIX = 'omnitrix_agent:';

    public function agentStatus()
    {
        $keys = Redis::keys(self::AGENT_HASH_PREFIX . '*');

        $agents = [];
        foreach ($keys as $key) {
            $agentData = Redis::hGetAll($key);

            if (empty($agentData)) {
                continue;
            }

            $agentData['AGENT_ID'] = (int) ($agentData['AGENT_ID'] ?? $this->extractAgentId($key));
            $agentData['CONTACT_TYPE'] = $this->decodeJsonField($agentData['CONTACT_TYPE'] ?? null);
            $agentData['SKILL'] = $this->decodeJsonField($agentData['SKILL'] ?? null);
            $agentData['STATUS'] = $this->normalizeStatus($agentData['STATUS'] ?? null);

            $agents[] = $agentData;
        }

        return jsonResponse('Agent statuses fetched successfully.', true, $agents);
    }

    private function extractAgentId(string $key): int
    {
        return (int) str_replace(self::AGENT_HASH_PREFIX, '', $key);
    }

    private function decodeJsonField(?string $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : [];
    }

    private function normalizeStatus(?string $status): string
    {
        $status = strtoupper((string) $status);
        $allowedStatuses = array_map(fn(UserStatus $case) => $case->value, UserStatus::cases());

        return in_array($status, $allowedStatuses, true) ? $status : UserStatus::OFFLINE->value;
    }
}
