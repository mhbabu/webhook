<?php

namespace App\Http\Controllers\Api\Message;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\StoreWrapUpConversation;
use App\Http\Requests\Message\UpdateWrapUpConversation;
use App\Http\Resources\Message\WrapUpConversationResource;
use App\Models\WrapUpConversation;
use Illuminate\Http\Request;

class WrapUpConversationController extends Controller
{
     public function index(Request $request)
    {
        $data       = $request->all();
        $pagination = !isset($data['pagination']) || $data['pagination'] === 'true' ? true : false;
        $page       = $data['page'] ?? 1;
        $perPage    = $data['per_page'] ?? 10;
        $searchText = $data['search'] ?? null;
        $searchBy   = $data['search_by'] ?? 'name';
        $sortBy     = $data['sort_by'] ?? 'id';
        $sortOrder  = $data['sort_order'] ?? 'asc';

        $query = WrapUpConversation::query();

        if ($searchText && $searchBy) {
            $query->where($searchBy, 'like', "%{$searchText}%");
        }

        $query->orderBy($sortBy, $sortOrder);

        if ($pagination) {
            $platforms = $query->paginate($perPage, ['*'], 'page', $page);
            return jsonResponseWithPagination('Wrap-up conversation list retrieved successfully', true, WrapUpConversationResource::collection($platforms)->response()->getData(true));
        }

        return jsonResponse('Wrap-up conversation list retrieved successfully', true, WrapUpConversationResource::collection($query->get()));
    }


    public function store(StoreWrapUpConversation $request)
    {
        $conversation = WrapUpConversation::create($request->validated());
        return jsonResponse('Wrap-up conversation created successfully', true, new WrapUpConversationResource($conversation), 201);
    }

    public function show($id)
    {
        $conversation = WrapUpConversation::find($id);

        if (!$conversation) {
            return jsonResponse('Wrap-up conversation not found', false, null, 404);
        }

        return jsonResponse('Wrap-up conversation retrieved successfully', true, new WrapUpConversationResource($conversation));
    }

    public function update(UpdateWrapUpConversation $request, $id)
    {
        $conversation = WrapUpConversation::find($id);

        if (!$conversation) {
            return jsonResponse('Wrap-up conversation not found', false, null, 404);
        }

        $conversation->update($request->only('name'));

        return jsonResponse('Wrap-up conversation updated successfully', true, new WrapUpConversationResource($conversation));
    }

    public function destroy($id)
    {
        $conversation = WrapUpConversation::find($id);

        if (!$conversation) {
            return jsonResponse('Wrap-up conversation not found', false, null, 404);
        }

        $conversation->delete();

        return jsonResponse('Wrap-up conversation deleted successfully', true, null, 200);
    }
}
