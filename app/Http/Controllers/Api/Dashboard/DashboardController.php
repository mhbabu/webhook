<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\AgentStatusResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function agentStatus(Request $request)
    {
        $statusFilter = $this->parseStatusFilter($request->input('filter.status'));

        $agents = collect(Redis::keys($this->agentKeyPattern()))
            ->map(fn(string $key) => $this->stripRedisPrefix($key))
            ->map(fn(string $key) => $this->buildAgentPayload($key))
            ->filter()
            ->filter(function (?array $agent) use ($statusFilter) {
                if ($agent === null) {
                    return false;
                }

                if ($statusFilter->isEmpty()) {
                    return true;
                }

                $status = strtoupper((string) ($agent['STATUS'] ?? ''));

                return $statusFilter->contains($status);
            })
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

    private function parseStatusFilter(array|string|null $value)
    {
        $rawValues = match (true) {
            is_null($value)        => [],
            is_array($value)       => $value,
            default                => explode(',', $value),
        };

        $allowed = collect(UserStatus::cases())->map->value;

        return collect($rawValues)
            ->flatMap(function ($item) {
                return is_array($item) ? $item : explode(',', (string) $item);
            })
            ->map(fn($status) => strtoupper(trim((string) $status)))
            ->filter()
            ->unique()
            ->filter(fn($status) => $allowed->contains($status))
            ->values();
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
