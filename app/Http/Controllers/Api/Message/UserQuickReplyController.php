<?php

namespace App\Http\Controllers\Api\Message;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserQuickReply;
use App\Http\Resources\Message\UserQuickReplyResource;
use App\Http\Requests\Message\StoreUserQuickReplyRequest;
use App\Http\Requests\Message\UpdateUserQuickReplyRequest;

class UserQuickReplyController extends Controller
{

    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $quickReplies = UserQuickReply::where('user_id', $userId)->get();
        return jsonResponse('User quick replies retrieved successfully', true, UserQuickReplyResource::collection($quickReplies));
    }

    public function store(StoreUserQuickReplyRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $quickReply = UserQuickReply::create($data);
        return jsonResponse('User quick reply created successfully', true, new UserQuickReplyResource($quickReply));
    }

    public function show(Request $request, $id)
    {
        $userId = $request->user()->id;
        $quickReply = UserQuickReply::where('user_id', $userId)->find($id);
        if (!$quickReply) {
            return jsonResponse('User quick reply not found', false);
        }
        return jsonResponse('User quick reply retrieved successfully', true, new UserQuickReplyResource($quickReply));
    }

    public function update(UpdateUserQuickReplyRequest $request, $id)
    {
        $userId = $request->user()->id;
        $quickReply = UserQuickReply::where('user_id', $userId)->find($id);
        if (!$quickReply) {
            return jsonResponse('User quick reply not found', false);
        }
        $quickReply->update($request->validated());
        return jsonResponse('User quick reply updated successfully', true, new UserQuickReplyResource($quickReply));
    }

    public function destroy(Request $request, $id)
    {
        $userId = $request->user()->id;
        $quickReply = UserQuickReply::where('user_id', $userId)->find($id);
        if (!$quickReply) {
            return jsonResponse('User quick reply not found', false);
        }
        $quickReply->delete();
        return jsonResponse('User quick reply deleted successfully', true);
    }
}
