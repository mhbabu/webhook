<?php

namespace App\Http\Controllers\Api\Threads;

use App\Http\Controllers\Controller;
use App\Http\Resources\Thread\CommentResource;
use App\Http\Resources\Thread\ConversationResource;
use App\Http\Resources\Thread\PostResource;
use App\Http\Resources\Thread\ThreadResource;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ThreadController extends Controller
{
    public function agentThreadList(Request $request)
    {
        $agentId = auth()->id();
        $data = $request->all();

        $pagination = ! isset($data['pagination']) || $data['pagination'] === 'true';
        $page = $data['page'] ?? 1;
        $perPage = $data['per_page'] ?? 10;

        // ✅ build query first
        $query = Conversation::with([
            'customer:id,name,email,phone',
            'comment:id,conversation_id,post_id,message,type',
        ])
            ->where('agent_id', $agentId)
            ->latest();

        // ✅ paginate or get
        if ($pagination) {
            $conversations = $query->paginate($perPage, ['*'], 'page', $page);

            return jsonResponseWithPagination(
                'Social page conversations retrieved successfully',
                true,
                ConversationResource::collection($conversations)->response()->getData(true)
            );
        }

        $conversations = $query->get();

        Log::info('Conversations: ', $conversations->toArray());

        return jsonResponse(
            'Social page conversations retrieved successfully',
            true,
            [
                'conversations' => ConversationResource::collection($conversations),
            ]
        );
    }

    public function getConversationWiseThread(Request $request, string $conversationId)
    {
        // Load conversation with customer
        $conversation = Conversation::with('customer')->findOrFail($conversationId);

        // Get the post_id from the first comment of this conversation (if exists)
        $postId = $conversation->commentTree->first()?->post_id;
        $post = Post::find($postId);

        Log::info('Post ID: '.$post);

        if (! $postId) {
            return response()->json([
                'status' => true,
                'message' => 'No comments found for this conversation',
                'data' => [],
            ]);
        }

        // Get all root comments of this post with recursive replies and customer info
        $comments = Comment::with([
            'customer:id,name',
            'replies.customer:id,name',
        ])
            ->where('post_id', $postId)
            ->whereNull('platform_parent_id') // root comments only
            ->orderBy('commented_at')
            ->get();

        Log::info('Comments with replies: ', $comments->toArray());

        return response()->json([
            'status' => true,
            'message' => 'Conversation thread retrieved successfully',
            'data' => [
                'post_id' => $postId,
                'content' => $post?->caption ?? '',
                'created_time' => $post?->created_at?->toDateTimeString(),
                'from' => $conversation->customer ? [
                    'id' => $conversation->customer->id,
                    'name' => $conversation->customer->name,
                ] : null,
                'attachments' => [], // Add attachment handling if needed
                'comments' => ThreadResource::collection($comments),
            ],
        ]);
    }

    public function getConversationWiseThread3(Request $request, string $conversationId)
    {
        $conversation = Conversation::with([
            'comment.post.media',
        ])->findOrFail($conversationId);

        $post = $conversation->comment?->post;

        if (! $post) {
            return response()->json([
                'status' => false,
                'message' => 'Post not found for this conversation',
                'data' => null,
            ], 404);
        }

        $comments = Comment::with([
            'customer:id,name',
            'replies.customer:id,name',
        ])
            ->where('post_id', $post->id)
            ->whereNull('platform_parent_id')
            ->orderBy('commented_at')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Conversation thread retrieved successfully',
            'data' => [
                'post' => new PostResource($post),
                'comments' => CommentResource::collection($comments),
            ],
        ]);
    }
}
