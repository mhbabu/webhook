<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class DashboardPendingTest extends TestCase
{
    public function test_pending_endpoint_returns_summary_and_sources(): void
    {
        Redis::shouldReceive('lRange')
            ->once()
            ->with('pending_messages_high', 0, -1)
            ->andReturn([
                json_encode(['source' => 'whatsapp']),
                json_encode(['source' => 'email']),
            ]);

        Redis::shouldReceive('lRange')
            ->once()
            ->with('pending_messages_medium', 0, -1)
            ->andReturn([
                json_encode(['source' => 'whatsapp']),
            ]);

        Redis::shouldReceive('lRange')
            ->once()
            ->with('pending_messages_low', 0, -1)
            ->andReturn([]);

        $response = $this->getJson('/api/v1/dashboard/pending');

        $response->assertOk()
            ->assertJson([
                'status' => true,
                'message' => 'Pending message counts fetched successfully.',
                'data' => [
                    'summary' => [
                        'high' => 2,
                        'medium' => 1,
                        'low' => 0,
                    ],
                    'sources' => [
                        'whatsapp' => 2,
                        'email' => 1,
                    ],
                ],
            ]);
    }

    public function test_pending_endpoint_handles_empty_lists(): void
    {
        Redis::shouldReceive('lRange')
            ->once()
            ->with('pending_messages_high', 0, -1)
            ->andReturn([]);

        Redis::shouldReceive('lRange')
            ->once()
            ->with('pending_messages_medium', 0, -1)
            ->andReturn([]);

        Redis::shouldReceive('lRange')
            ->once()
            ->with('pending_messages_low', 0, -1)
            ->andReturn([]);

        $response = $this->getJson('/api/v1/dashboard/pending');

        $response->assertOk()
            ->assertJson([
                'status' => true,
                'data' => [
                    'summary' => [
                        'high' => 0,
                        'medium' => 0,
                        'low' => 0,
                    ],
                    'sources' => [],
                ],
            ]);
    }
}
