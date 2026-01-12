<?php

namespace App\Http\Controllers\Api\conversation;

use App\Http\Controllers\Controller;
use App\Models\InteractionType;

class InteractionTypeController extends Controller
{
    /**
     * GET /api/v1/conversations/type
     */
    public function index()
    {
        return jsonResponse(
            'Fetched successfully',
            true,
            InteractionType::select('id', 'name')
                ->where('is_active', true)
                ->get()
        );
    }
}
