<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\AgentStatusResource;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function agentStatus(Request $request)
    {
        $statusFilter = $this->parseStatusFilter($request->input('filter.status'));
        $agentFilter = $this->parseAgentFilter($request->input('filter.agent'));

        $agents = collect(Redis::keys($this->agentKeyPattern()))
            ->map(fn(string $key) => $this->stripRedisPrefix($key))
            ->map(fn(string $key) => $this->buildAgentPayload($key))
            ->filter()
            ->filter(function (?array $agent) use ($statusFilter, $agentFilter) {
                if ($agent === null) {
                    return false;
                }

                if (! $statusFilter->isEmpty()) {
                    $status = strtoupper((string) ($agent['STATUS'] ?? ''));
                    if (! $statusFilter->contains($status)) {
                        return false;
                    }
                }

                if (! $agentFilter->isEmpty()) {
                    $id = (int) ($agent['AGENT_ID'] ?? 0);
                    if (! $agentFilter->contains($id)) {
                        return false;
                    }
                }

                return true;
            })
            ->values();

        if ($agents->isEmpty()) {
            return  jsonResponse('Empty Content.', true, [], 204);
        }

        $resource = AgentStatusResource::collection($agents)->resolve();
        $summary = $this->buildStatusSummary($agents, $statusFilter);

        return jsonResponse('Agent statuses fetched successfully.', true, [
            'summary' => $summary,
            'agents' => $resource,
        ]);
    }

    public function pending()
    {
        $priorities = ['high', 'medium', 'low'];
        $summary = [];
        $sourceCounts = [];

        foreach ($priorities as $priority) {
            $messages = $this->fetchPendingMessages($priority);
            $summary[$priority] = $messages->count();

            foreach ($messages as $message) {
                $source = trim((string) ($message['source'] ?? ''));

                if ($source === '') {
                    continue;
                }

                $sourceCounts[$source] = ($sourceCounts[$source] ?? 0) + 1;
            }
        }

        return jsonResponse('Pending message counts fetched successfully.', true, [
            'summary' => $summary,
            'sources' => $sourceCounts,
        ]);
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

    private function parseStatusFilter(array|string|null $value): Collection
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

    private function parseAgentFilter(array|string|int|null $value): Collection
    {
        $rawValues = match (true) {
            is_null($value)        => [],
            is_array($value)       => $value,
            is_numeric($value)     => [$value],
            default                => explode(',', (string) $value),
        };

        return collect($rawValues)
            ->flatMap(function ($item) {
                return is_array($item) ? $item : explode(',', (string) $item);
            })
            ->map(fn($id) => (int) trim((string) $id))
            ->filter(fn($id) => $id > 0)
            ->unique()
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

    private function buildStatusSummary(Collection $agents, Collection $statusFilter): array
    {
        $counts = $agents->groupBy(function ($agent) {
            return strtoupper((string) ($agent['STATUS'] ?? ''));
        })->map->count();

        $statuses = $statusFilter->isEmpty()
            ? collect(UserStatus::cases())->map->value
            : $statusFilter;

        return $statuses
            ->mapWithKeys(fn($status) => [$status => (int) ($counts[$status] ?? 0)])
            ->toArray();
    }

    private function fetchPendingMessages(string $priority): Collection
    {
        $items = Redis::lRange($this->pendingListKey($priority), 0, -1);

        return collect($items)
            ->map(fn($item) => $this->decodePendingItem($item))
            ->filter();
    }

    private function pendingListKey(string $priority): string
    {
        return "pending_messages_{$priority}";
    }

    private function decodePendingItem(?string $value): ?array
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
