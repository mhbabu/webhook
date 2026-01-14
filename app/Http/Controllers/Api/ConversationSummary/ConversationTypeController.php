<?php

namespace App\Http\Controllers\Api\ConversationSummary;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConversationSummary\ConversationTypeRequest;
use App\Http\Resources\Conversation\ConversationTypeResource;
use App\Models\ConversationType;
use Illuminate\Http\Request;

class ConversationTypeController extends Controller
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

        $query = ConversationType::query();

        if ($searchText && $searchBy) {
            $query->where($searchBy, 'like', "%{$searchText}%");
        }

        $query->orderBy($sortBy, $sortOrder);

        if ($pagination) {
            $items = $query->paginate($perPage, ['*'], 'page', $page);

            return jsonResponseWithPagination(
                'Conversation types retrieved successfully',
                true,
                ConversationTypeResource::collection($items)->response()->getData(true)
            );
        }

        return jsonResponse(
            'Conversation types retrieved successfully',
            true,
            ConversationTypeResource::collection($query->get())
        );
    }

    public function store(ConversationTypeRequest $request)
    {
        $type = ConversationType::create($request->validated());

        return jsonResponse(
            'Conversation type created successfully',
            true,
            new ConversationTypeResource($type)
        );
    }

    public function show($id)
    {
        $type = ConversationType::find($id);
        if (! $type) {
            return jsonResponse('Conversation type not found', false);
        }

        return jsonResponse(
            'Conversation type retrieved successfully',
            true,
            new ConversationTypeResource($type)
        );
    }

    public function update(ConversationTypeRequest $request, $id)
    {
        $type = ConversationType::find($id);
        if (! $type) {
            return jsonResponse('Conversation type not found', false);
        }

        $type->update($request->validated());

        return jsonResponse(
            'Conversation type updated successfully',
            true,
            new ConversationTypeResource($type)
        );
    }

    public function destroy($id)
    {
        $type = ConversationType::find($id);
        if (! $type) {
            return jsonResponse('Conversation type not found', false);
        }

        $type->delete();

        return jsonResponse('Conversation type deleted successfully', true);
    }
}
