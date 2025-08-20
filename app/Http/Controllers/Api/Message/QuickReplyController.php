<?php

namespace App\Http\Controllers\Api\Message;


use App\Http\Controllers\Controller;
use App\Models\QuickReply;
use App\Http\Resources\Message\QuickReplyResource;
use App\Http\Requests\Message\StoreQuickReplyRequest;
use App\Http\Requests\Message\UpdateQuickReplyRequest;

class QuickReplyController extends Controller
{
    public function index()
    {
        $quickReplies = QuickReply::all();
        return jsonResponse('Quick replies retrieved successfully', true, QuickReplyResource::collection($quickReplies));
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
