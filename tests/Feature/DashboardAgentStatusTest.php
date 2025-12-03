<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class DashboardAgentStatusTest extends TestCase
{
    public function test_agent_status_endpoint_returns_data_from_redis(): void
    {
        Redis::shouldReceive('keys')
            ->once()
            ->with('agent:*')
            ->andReturn(['omnitrix_agent:2']);

        Redis::shouldReceive('hGetAll')
            ->once()
            ->with('agent:2')
            ->andReturn([
                'AVAILABLE_SCOPE' => '5',
                'CONTACT_TYPE' => '[]',
                'AGENT_ID' => '2',
                'SKILL' => '["whatsapp","facebook_messenger"]',
                'STATUS' => 'AVAILABLE',
                'BUSYSINCE' => '2024-01-01 10:00:00',
                'AGENT_TYPE' => 'NORMAL',
                'MAX_SCOPE' => '5',
            ]);

        $response = $this->getJson('/api/v1/dashboard/agent-status');

        $response->assertOk()
            ->assertJson([
                'status' => true,
                'message' => 'Agent statuses fetched successfully.',
                'data' => [
                    'summary' => [
                        'AVAILABLE' => 1,
                        'OCCUPIED' => 0,
                        'BREAK_REQUEST' => 0,
                        'BREAK' => 0,
                        'OFFLINE' => 0,
                    ],
                    'agents' => [
                        [
                            'AGENT_ID' => 2,
                            'STATUS' => 'AVAILABLE',
                            'CONTACT_TYPE' => [],
                            'SKILL' => ['whatsapp', 'facebook_messenger'],
                        ],
                    ],
                ],
            ]);
    }

    public function test_agent_status_endpoint_filters_by_status(): void
    {
        Redis::shouldReceive('keys')
            ->once()
            ->with('agent:*')
            ->andReturn(['omnitrix_agent:2', 'omnitrix_agent:3']);

        Redis::shouldReceive('hGetAll')
            ->once()
            ->with('agent:2')
            ->andReturn([
                'AGENT_ID' => '2',
                'STATUS' => 'AVAILABLE',
            ]);

        Redis::shouldReceive('hGetAll')
            ->once()
            ->with('agent:3')
            ->andReturn([
                'AGENT_ID' => '3',
                'STATUS' => 'OCCUPIED',
            ]);

        $response = $this->getJson('/api/v1/dashboard/agent-status?filter[status]=AVAILABLE,OCCUPIED');

        $response->assertOk()
            ->assertJsonCount(2, 'data.agents')
            ->assertJsonPath('data.summary', [
                'AVAILABLE' => 1,
                'OCCUPIED' => 1,
            ])
            ->assertJsonMissingPath('data.summary.BREAK')
            ->assertJsonFragment([
                'AGENT_ID' => 2,
                'STATUS' => 'AVAILABLE',
            ])
            ->assertJsonFragment([
                'AGENT_ID' => 3,
                'STATUS' => 'OCCUPIED',
            ]);
    }
}
