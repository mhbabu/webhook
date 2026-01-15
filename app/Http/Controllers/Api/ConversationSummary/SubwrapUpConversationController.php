<?php

namespace App\Http\Controllers\Api\ConversationSummary;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConversationSummary\ConversationSubWrapUp\StoreSubWrapUpConversationRequest;
use App\Http\Requests\ConversationSummary\ConversationSubWrapUp\UpdateSubWrapUpConversationRequest;
use App\Http\Resources\ConversationSummary\SubwrapUpConversationResource;
use App\Models\SubwrapUpConversation;
use Illuminate\Http\Request;

class SubwrapUpConversationController extends Controller
{
    public function index(Request $request)
    {
        $data         = $request->all();
        $pagination   = ! isset($data['pagination']) || $data['pagination'] === 'true';
        $page         = $data['page'] ?? 1;
        $perPage      = $data['per_page'] ?? 10;
        $searchText   = $data['search'] ?? null;
        $searchBy     = $data['search_by'] ?? 'name';
        $sortBy       = $data['sort_by'] ?? 'id';
        $sortOrder    = $data['sort_order'] ?? 'asc';

        $query = SubwrapUpConversation::with('wrapUpConversation');

        if ($searchText && $searchBy) {
            $query->where($searchBy, 'like', "%{$searchText}%");
        }

        $query->orderBy($sortBy, $sortOrder);

        if ($pagination) {
            $items = $query->paginate($perPage, ['*'], 'page', $page);

            return jsonResponseWithPagination('Sub-wrap-up conversations retrieved successfully', true, SubwrapUpConversationResource::collection($items)->response()->getData(true)
            );
        }

        return jsonResponse('Sub-wrap-up conversations retrieved successfully', true, SubwrapUpConversationResource::collection($query->get()));
    }

    public function store(StoreSubWrapUpConversationRequest $request)
    {
        $sub = SubwrapUpConversation::create($request->validated());
        return jsonResponse('Sub-wrap-up conversation created successfully', true, new SubwrapUpConversationResource($sub));
    }

    public function show($id)
    {
        $sub = WrapUpSubConversation::with('wrapUpConversation')->find($id);
        if (! $sub) {
            return jsonResponse('Wrap-up sub conversation not found', false);
        }

        return jsonResponse('Sub-wrap-up conversation retrieved successfully', true, new WrapUpSubConversationResource($sub));
    }

    public function update(UpdateSubWrapUpConversationRequest $request, $wrap_up_conversation)
    {
        $sub = SubwrapUpConversation::find($wrap_up_conversation);
        if (! $sub) {
            return jsonResponse('Wrap-up sub conversation not found', false);
        }

        $sub->update($request->validated());
        return jsonResponse('Sub-wrap-up conversation updated successfully', true, new SubwrapUpConversationResource($sub));
    }

    public function destroy($id)
    {
        $sub = SubwrapUpConversation::find($id);
        if (! $sub) {
            return jsonResponse('Wrap-up sub conversation not found', false);
        }

        $sub->delete();

        return jsonResponse('Sub-wrap-up conversation deleted successfully', true);
    }
}
