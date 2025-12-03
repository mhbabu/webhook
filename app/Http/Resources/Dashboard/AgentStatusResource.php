<?php

namespace App\Http\Resources\Dashboard;

use App\Enums\UserStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'AGENT_ID' => (int) ($this->resource['AGENT_ID'] ?? 0),
            'AGENT_TYPE' => $this->resource['AGENT_TYPE'] ?? 'NORMAL',
            'STATUS' => $this->normalizeStatus($this->resource['STATUS'] ?? null),
            'MAX_SCOPE' => (int) ($this->resource['MAX_SCOPE'] ?? 0),
            'AVAILABLE_SCOPE' => (int) ($this->resource['AVAILABLE_SCOPE'] ?? 0),
            'CONTACT_TYPE' => $this->decodeJsonField($this->resource['CONTACT_TYPE'] ?? null),
            'SKILL' => $this->decodeJsonField($this->resource['SKILL'] ?? null),
            'BUSYSINCE' => $this->resource['BUSYSINCE'] ?? null,
        ];
    }

    private function decodeJsonField(?string $value): array
    {
        if (! is_string($value) || trim($value) === '') {
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
