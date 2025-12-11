<?php

namespace App\Http\Controllers\Api\Report;

use App\Exports\Report\ConversationDetailReportExport;
use App\Exports\Report\ConversationReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ConversationReportRequest;
use App\Http\Resources\Message\ConversationInfoResource;
use App\Http\Resources\Report\ConversationDetailReportResource;
use App\Http\Resources\Report\ConversationReportResource;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function conversationReport(ConversationReportRequest $request)
    {
        $data       = $request->all();
        $pagination = isset($data['pagination']) && $data['pagination'] === 'true';
        $page       = $data['page'] ?? 1;
        $perPage    = $data['per_page'] ?? 10;

        if (!empty($data['download']) && $data['download'] == true) {
            return Excel::download(new ConversationReportExport($data), 'Conversation_Report_' . date('Y_m_d_H_i_s') . '.csv');
        }

        $query = Conversation::getConversationInfo($data);
        if ($pagination) {
            $conversations = $query->paginate($perPage, ['*'], 'page', $page);

            return jsonResponseWithPagination('Conversations retrieved successfully', true, ConversationReportResource::collection($conversations)->response()->getData(true));
        }

        $conversations = $query->get();

        return jsonResponse('Conversations retrieved successfully', true, ConversationReportResource::collection($conversations));
    }


    public function conversationDetails(Request $request)
    {
        $conversation = Conversation::where('trace_id', $request->trace_id)->first();

        if (!$conversation) {
            return jsonResponse('Conversation not found', false, null, 404);
        }

        if (!empty($request->download) && $request->download == true) {
            return Excel::download(new ConversationDetailReportExport($request->trace_id), 'Conversation_Details_Report_' . date('Y_m_d_H_i_s') . '.csv');
        }

        return jsonResponse('Conversations retrieved successfully', true, new ConversationDetailReportResource($conversation));
    }
}
