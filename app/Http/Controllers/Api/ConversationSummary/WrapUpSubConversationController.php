<?php

namespace App\Http\Controllers\Api\ConversationSummary;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConversationSummary\WrapUpSubConversationRequest;
use App\Http\Resources\Conversation\WrapUpSubConversationResource;
use App\Models\WrapUpSubConversation;
use Illuminate\Http\Request;

class WrapUpSubConversationController extends Controller
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

        $query = WrapUpSubConversation::query();

        if ($request->filled('wrap_up_conversation_id')) {
            $query->where('wrap_up_conversation_id', $request->wrap_up_conversation_id);
        }

        if ($searchText && $searchBy) {
            $query->where($searchBy, 'like', "%{$searchText}%");
        }

        $query->orderBy($sortBy, $sortOrder);

        if ($pagination) {
            $items = $query->paginate($perPage, ['*'], 'page', $page);

            return jsonResponseWithPagination(
                'Wrap-up sub conversations retrieved successfully',
                true,
                WrapUpSubConversationResource::collection($items)->response()->getData(true)
            );
        }

        return jsonResponse(
            'Wrap-up sub conversations retrieved successfully',
            true,
            WrapUpSubConversationResource::collection($query->get())
        );
    }

    public function store(WrapUpSubConversationRequest $request)
    {
        $sub = WrapUpSubConversation::create($request->validated());

        return jsonResponse(
            'Wrap-up sub conversation created successfully',
            true,
            new WrapUpSubConversationResource($sub)
        );
    }

    public function show($id)
    {
        $sub = WrapUpSubConversation::find($id);
        if (! $sub) {
            return jsonResponse('Wrap-up sub conversation not found', false);
        }

        return jsonResponse(
            'Wrap-up sub conversation retrieved successfully',
            true,
            new WrapUpSubConversationResource($sub)
        );
    }

    public function update(WrapUpSubConversationRequest $request, $id)
    {
        $sub = WrapUpSubConversation::find($id);
        if (! $sub) {
            return jsonResponse('Wrap-up sub conversation not found', false);
        }

        $sub->update($request->validated());

        return jsonResponse(
            'Wrap-up sub conversation updated successfully',
            true,
            new WrapUpSubConversationResource($sub)
        );
    }

    public function destroy($id)
    {
        $sub = WrapUpSubConversation::find($id);
        if (! $sub) {
            return jsonResponse('Wrap-up sub conversation not found', false);
        }

        $sub->delete();

        return jsonResponse('Wrap-up sub conversation deleted successfully', true);
    }
}
