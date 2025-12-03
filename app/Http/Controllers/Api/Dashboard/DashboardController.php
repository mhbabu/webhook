<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;


class DashboardController extends Controller
{

    public function agentStatus()
    {
        return response()->json([
            'agents' => [
                ['id' => 1, 'name' => 'Agent 1', 'status' => 'online'],
                ['id' => 2, 'name' => 'Agent 2', 'status' => 'offline'],
                ['id' => 3, 'name' => 'Agent 3', 'status' => 'busy'],
            ]
        ]);
    }
}
