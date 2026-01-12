<?php

namespace App\Http\Controllers\Api\conversation;

use App\Http\Controllers\Controller;
use App\Models\InteractionType;

class CustomerModeController extends Controller
{
    /**
     * GET /api/v1/conversations/customer-mode
     */
    public function index()
    {
        return jsonResponse(
            'Fetched successfully',
            true,
            InteractionType::with('customerModes:id,interaction_type_id,name')
                ->where('is_active', true)
                ->get()
        );
    }
}
