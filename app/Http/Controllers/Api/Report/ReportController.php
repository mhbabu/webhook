<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ConversationReportRequest;
use App\Http\Resources\Report\ConversationReportResource;
use App\Models\Conversation;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function conversationReport(ConversationReportRequest $request)
    {
        $data       = $request->all();
        $pagination = isset($data['pagination']) && $data['pagination'] === 'true';
        $page       = $data['page'] ?? 1;
        $perPage    = $data['per_page'] ?? 10;

        $query = Conversation::with([
            'customer:id,name,email,phone,username',
            'agent:id,name,email,employee_id',
            'lastMessage:id,content,delivered_at,created_at',
            'wrapUp:id,name',
            'endedBy:id,name'
        ])
            ->whereDate('created_at', '>=', $data['start_date'])
            ->whereDate('created_at', '<=', $data['end_date'])
            ->latest();

        if ($pagination) {
            $conversations = $query->paginate($perPage, ['*'], 'page', $page);
            return jsonResponseWithPagination('Conversations retrieved successfully', true, ConversationReportResource::collection($conversations)->response()->getData(true));
        }

        $conversations = $query->get();
        return jsonResponse('Conversations retrieved successfully', true, ConversationReportResource::collection($conversations));
    }


    public function conversationDetails() {}
}
