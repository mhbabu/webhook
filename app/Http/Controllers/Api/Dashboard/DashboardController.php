<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\AgentStatusResource;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function agentStatus()
    {
        $agents = collect(Redis::keys($this->agentKeyPattern()))
            ->map(fn(string $key) => $this->stripRedisPrefix($key))
            ->map(fn(string $key) => $this->buildAgentPayload($key))
            ->filter()
            ->values();

        $resource = AgentStatusResource::collection($agents)->resolve();

        return jsonResponse('Agent statuses fetched successfully.', true, $resource);
    }

    private function buildAgentPayload(string $key): ?array
    {
        $agentData = Redis::hGetAll($key);

        if (empty($agentData)) {
            return null;
        }

        $agentData['AGENT_ID'] = (int) ($agentData['AGENT_ID'] ?? $this->extractAgentId($key));

        return $agentData;
    }

    private function extractAgentId(string $key): int
    {
        return (int) str_replace('agent:', '', $key);
    }

    private function agentKeyPattern(): string
    {
        return 'agent:*';
    }

    private function stripRedisPrefix(string $key): string
    {
        $prefix = (string) config('database.redis.options.prefix', '');

        if ($prefix === '' || ! Str::startsWith($key, $prefix)) {
            return $key;
        }

        return substr($key, strlen($prefix));
    }
}
