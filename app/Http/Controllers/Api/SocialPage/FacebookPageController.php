<?php

namespace App\Http\Controllers\Api\SocialPage;

use App\Http\Controllers\Controller;
use App\Http\Requests\SocialPage\Facebook\PostCommentReplyRequest;
use App\Http\Resources\SocialPage\ConversationPageDetailResource;
use App\Http\Resources\SocialPage\ConversationPageResource;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostCommentReply;
use App\Models\Customer;
use App\Models\Platform;
use App\Models\Conversation;
use Illuminate\Support\Facades\DB;
use App\Services\SocialPage\FacebookPageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FacebookPageController extends Controller
{
    protected FacebookPageService $facebookPageService;

    public function __construct(FacebookPageService $facebookPageService)
    {
        $this->facebookPageService = $facebookPageService;
    }

    public function getAgentConversationList(Request $request)
    {
        $agentId = auth()->id();

        $pagination = filter_var($request->input('pagination', false), FILTER_VALIDATE_BOOLEAN);
        $page       = (int) $request->input('page', 1);
        $perPage    = (int) $request->input('per_page', 10);
        $query      = Conversation::whereIn('platform', ['facebook', 'instagram'])->where('agent_id', $agentId)->latest();

        if ($pagination) {
            $conversations = $query->paginate($perPage, ['*'], 'page', $page);

            return jsonResponseWithPagination('Social page conversations retrieved successfully', true, ConversationPageResource::collection($conversations)->response()->getData(true));
        }

        $conversations = $query->get();
        return jsonResponse('Social page conversations retrieved successfully', true, ConversationPageResource::collection($conversations));
    }

    public function conversationWisePostDetails($conversationId)
    {
        $conversation = Conversation::find($conversationId);

        if (!$conversation) {
            return jsonResponse('Conversation not found', false);
        }
        return jsonResponse('Social page conversations retrieved successfully', true, new ConversationPageDetailResource($conversation));
    }

    public function replyAComment(PostCommentReplyRequest $request, $conversationId)
    {
        $conversation = Conversation::find($conversationId);

        if (!$conversation) {
            return jsonResponse('Conversation not found', false);
        }

        if ($conversation->type === 'comment') {
            return jsonResponse('Conversation is not a comment type', false);
        }

        try {
            $reply = $this->facebookPageService->replyToComment($conversation->comment->platform_comment_id, $request->input('content'));

            $postCommentReply = new PostCommentReply();
            $postCommentReply->post_comment_id = $conversation->comment->id;
            $postCommentReply->platform_reply_id = $reply['data']['id'] ?? null; // <-- fixed here
            $postCommentReply->content = $request->input('content');
            $postCommentReply->user_id = auth()->id();
            $postCommentReply->save();

            // ✅ End the conversation
            $conversation->end_at     = now();
            $conversation->wrap_up_id = 30; //'Agent replied and the conversation was ended', from WrapUpConversationFactory
            $conversation->ended_by   = $conversation->agent_id;
            $conversation->save();

            updateUserInRedis($conversation->agent, $conversation);

            return jsonResponse('Reply sent successfully', true);
        } catch (\Exception $e) {
            Log::error('Error replying to comment: ' . $e->getMessage());
            return jsonResponse('Failed to send reply', false);
        }
    }
}
