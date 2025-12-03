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
            ->with('omnitrix_agent:*')
            ->andReturn(['omnitrix_agent:2']);

        Redis::shouldReceive('hGetAll')
            ->once()
            ->with('omnitrix_agent:2')
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
                    [
                        'AGENT_ID' => 2,
                        'STATUS' => 'AVAILABLE',
                        'CONTACT_TYPE' => [],
                        'SKILL' => ['whatsapp', 'facebook_messenger'],
                    ],
                ],
            ]);
    }
}
