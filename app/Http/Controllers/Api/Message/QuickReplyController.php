<?php

namespace App\Http\Controllers\Api\Message;


use App\Http\Controllers\Controller;
use App\Models\QuickReply;
use App\Http\Resources\Message\QuickReplyResource;
use App\Http\Requests\Message\StoreQuickReplyRequest;
use App\Http\Requests\Message\UpdateQuickReplyRequest;
use Illuminate\Http\Request;

class QuickReplyController extends Controller
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

        $query = QuickReply::query();

        if ($searchText && $searchBy) {
            $query->where($searchBy, 'like', "%{$searchText}%");
        }

        $query->orderBy($sortBy, $sortOrder);

        if ($pagination) {
            $quickReplies = $query->paginate($perPage, ['*'], 'page', $page);
            return jsonResponseWithPagination('Quick replies retrieved successfully', true, QuickReplyResource::collection($quickReplies)->response()->getData(true));
        }

        return jsonResponse('Quick replies retrieved successfully', true, QuickReplyResource::collection($query->get()));
    }

    public function store(StoreQuickReplyRequest $request)
    {
        $data       = $request->validated();
        $quickReply = QuickReply::create($data);
        return jsonResponse('Quick reply created successfully', true, new QuickReplyResource($quickReply));
    }

    public function show($id)
    {
        $quickReply = QuickReply::find($id);
        if (!$quickReply) {
            return jsonResponse('Quick reply not found', false);
        }
        return jsonResponse('Quick reply retrieved successfully', true, new QuickReplyResource($quickReply));
    }

    public function update(UpdateQuickReplyRequest $request, $id)
    {
        $quickReply = QuickReply::find($id);
        if (!$quickReply) {
            return jsonResponse('Quick reply not found', false);
        }
        $quickReply->update($request->validated());
        return jsonResponse('Quick reply updated successfully', true, new QuickReplyResource($quickReply));
    }

    public function destroy($id)
    {
        $quickReply = QuickReply::find($id);
        if (!$quickReply) {
            return jsonResponse('Quick reply not found', false);
        }
        $quickReply->delete();
        return jsonResponse('Quick reply deleted successfully', true);
    }
}
