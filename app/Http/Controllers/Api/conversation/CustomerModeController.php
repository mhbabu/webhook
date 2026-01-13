<?php

namespace App\Http\Controllers\Api\Conversation;

use App\Http\Controllers\Controller;
use App\Http\Resources\Conversation\CustomerModeResource;
use App\Models\CustomerMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerModeController extends Controller
{
    /**
     * List customer modes
     */
    public function index()
    {
        $modes = CustomerMode::orderBy('id')->get();

        return jsonResponse(
            'Customer modes fetched successfully',
            true,
            CustomerModeResource::collection($modes)
        );
    }

    /**
     * Create customer mode
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:customer_modes,name',
            'is_active' => 'sometimes|boolean',
        ]);

        Log::info('Creating customer mode', $validated);

        $mode = CustomerMode::create($validated);

        return jsonResponse(
            'Customer mode created successfully',
            true,
            new CustomerModeResource($mode)
        );
    }

    /**
     * Update customer mode (partial update supported)
     */
    public function update(Request $request, $id)
    {
        $mode = CustomerMode::findOrFail($id);

        Log::info('Before update', $mode->toArray());
        Log::info('Request data', $request->all());

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100|unique:customer_modes,name,'.$id,
            'is_active' => 'sometimes|boolean',
        ]);

        Log::info('Validated data', $validated);

        $mode->update($validated);

        return jsonResponse(
            'Customer mode updated successfully',
            true,
            new CustomerModeResource($mode->fresh())
        );
    }

    /**
     * Delete customer mode
     */
    public function destroy($id)
    {
        CustomerMode::findOrFail($id)->delete();

        return jsonResponse('Customer mode deleted successfully', true);
    }
}
