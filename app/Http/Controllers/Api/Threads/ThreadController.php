<?php

namespace App\Http\Controllers\Api\Threads;

use App\Http\Controllers\Controller;
// use App\Http\Resources\Message\ConversationResource;
use App\Http\Resources\Thread\CommentResource;
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

        return jsonResponse(
            'Social page conversations retrieved successfully',
            true,
            [
                'conversations' => ThreadResource::collection($conversations),
            ]
        );
    }

    public function getConversationWiseThread1(Request $request, string $conversationId)
    {
        // 1. Load conversation with post + root comment
        $conversation = Conversation::with([
            'comment:id,conversation_id,post_id,message,type',
        ])->where('id', $conversationId)->firstOrFail();

        // 2. Root comment of this conversation
        $rootComment = $conversation->comment->post_id
            ? $conversation->comment
            : null;

        Log::info('Root Comment: ', $rootComment ? $rootComment->toArray() : []);
        // $postId = $conversation->comment->post_id;

        // 3. Load replies recursively (level 2, 3, ...)
        $replies = $rootComment
            ? $this->loadReplies($rootComment)
            : collect();

        return jsonResponse(
            'Conversation thread retrieved successfully',
            true,
            [
                'conversation' => [
                    'id' => $conversation->id,
                    'platform' => $conversation->platform,
                    'trace_id' => $conversation->trace_id,
                    'started_at' => $conversation->started_at?->toDateTimeString(),
                    'end_at' => $conversation->end_at?->toDateTimeString(),
                ],

                'post' => $rootComment?->post
                ? [
                    'id' => $rootComment->post->id,
                    'caption' => $rootComment->post->caption,
                    'posted_at' => $rootComment->post->posted_at,
                ]
                : null,

                'customer' => $conversation->customer,
                'comment' => $rootComment,

                'replies' => $replies,
            ]
        );
    }

    private function loadReplies(Comment $comment)
    {
        return $comment->replies()
            ->with(['customer'])
            ->orderBy('commented_at')
            ->get()
            ->map(function ($reply) {
                return [
                    'id' => $reply->id,
                    'type' => $reply->type,
                    'message' => $reply->message,
                    'author' => $reply->author_name,
                    'customer' => $reply->customer,
                    'created_at' => $reply->commented_at?->toDateTimeString(),
                    'replies' => $this->loadReplies($reply), // recursion
                ];
            });
    }

    public function getConversationWiseThread2(Request $request, string $conversationId)
    {
        // Load conversation with customer
        $conversation = Conversation::with('customer')->findOrFail($conversationId);

        // Get the post_id from the first comment of this conversation (if exists)
        $postId = $conversation->commentTree->first()?->post_id;

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
                'caption' => $post?->caption ?? '',
                'comments' => ThreadResource::collection($comments),
            ],
        ]);
    }

    public function getConversationWiseThread(Request $request, string $conversationId)
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
