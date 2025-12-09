<?php

namespace App\Http\Controllers\Api\Report;

use App\Exports\Report\ConversationReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ConversationReportRequest;
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
            return Excel::download(new ConversationReportExport($data), 'Conversation_Report_' . date('Y_m_d_H_i_s') . '.xlsx');
        }

        $query = Conversation::getConversationInfo($data);
        if ($pagination) {
            $conversations = $query->paginate($perPage, ['*'], 'page', $page);

            return jsonResponseWithPagination('Conversations retrieved successfully', true, ConversationReportResource::collection($conversations)->response()->getData(true));
        }

        $conversations = $query->get();

        return jsonResponse('Conversations retrieved successfully', true, ConversationReportResource::collection($conversations));
    }


    public function conversationDetails() {}
}
