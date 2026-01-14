<?php

namespace App\Http\Controllers\Api\ConversationSummary;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConversationSummary\CustomerModeRequest;
use App\Http\Resources\ConversationSummary\CustomerModeResource;
use App\Models\CustomerMode;
use Illuminate\Http\Request;

class CustomerModeController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->all();
        $pagination = ! isset($data['pagination']) || $data['pagination'] === 'true';
        $page = $data['page'] ?? 1;
        $perPage = $data['per_page'] ?? 10;
        $searchText = $data['search'] ?? null;
        $searchBy = $data['search_by'] ?? 'name';
        $sortBy = $data['sort_by'] ?? 'id';
        $sortOrder = $data['sort_order'] ?? 'asc';

        $query = CustomerMode::query();

        if ($searchText && $searchBy) {
            $query->where($searchBy, 'like', "%{$searchText}%");
        }

        $query->orderBy($sortBy, $sortOrder);

        if ($pagination) {
            $items = $query->paginate($perPage, ['*'], 'page', $page);

            return jsonResponseWithPagination(
                'Customer modes retrieved successfully',
                true,
                CustomerModeResource::collection($items)->response()->getData(true)
            );
        }

        return jsonResponse(
            'Customer modes retrieved successfully',
            true,
            CustomerModeResource::collection($query->get())
        );
    }

    public function store(CustomerModeRequest $request)
    {
        $mode = CustomerMode::create($request->validated());

        return jsonResponse(
            'Customer mode created successfully',
            true,
            new CustomerModeResource($mode)
        );
    }

    public function show($id)
    {
        $mode = CustomerMode::find($id);
        if (! $mode) {
            return jsonResponse('Customer mode not found', false);
        }

        return jsonResponse(
            'Customer mode retrieved successfully',
            true,
            new CustomerModeResource($mode)
        );
    }

    public function update(CustomerModeRequest $request, $id)
    {
        $mode = CustomerMode::find($id);
        if (! $mode) {
            return jsonResponse('Customer mode not found', false);
        }

        $mode->update($request->validated());

        return jsonResponse(
            'Customer mode updated successfully',
            true,
            new CustomerModeResource($mode)
        );
    }

    public function destroy($id)
    {
        $mode = CustomerMode::find($id);
        if (! $mode) {
            return jsonResponse('Customer mode deleted successfully', false);
        }

        $mode->delete();

        return jsonResponse('Customer mode deleted successfully', true);
    }
}
