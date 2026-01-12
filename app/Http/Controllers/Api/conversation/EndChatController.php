<?php

namespace App\Http\Controllers\Api\conversation;

use App\Http\Controllers\Controller;
use App\Models\CustomerMode;
use App\Models\InteractionType;
use Illuminate\Http\Request;

class EndChatController extends Controller
{
    /**
     * GET /api/v1/conversations/type
     */
    public function interaction()
    {
        $interactionTypes = InteractionType::select('id', 'name')->get();

        return jsonResponse(
            'Interaction types fetched successfully',
            true,
            $interactionTypes
        );
    }

    /**
     * GET /api/v1/conversations/customer-mode
     */
    public function customerMode()
    {
        $customerModes = CustomerMode::select('id', 'name')->get();

        return jsonResponse(
            'Customer modes fetched successfully',
            true,
            $customerModes
        );
    }

    /**
     * POST /api/v1/conversations/disposition
     */
    public function disposition(Request $request)
    {
        // future logic (store interaction + mode + remark)
        return jsonResponse('Disposition successfully', true);
    }
}
