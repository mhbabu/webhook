<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Message\MessageResource;

class ConversationDetailReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $conversation = $this->resource;

        return [
            'id'                => $conversation->id,
            'session_id'        => $conversation->trace_id,
            'session_start_time'=> $conversation->agent_assigned_at?->format('Y-m-d H:i:s'),
            'agent_route_time'  => $conversation->agent_first_response_at?->format('Y-m-d H:i:s'),
            'session_end_time'  => $conversation->end_at?->format('Y-m-d H:i:s'),
            'channel'           => $conversation->platform,
            'status'            => $this->resolveStatus($conversation),
            'agent_name'        => optional($conversation->agent)->name,
            'customer_name'     => $conversation->customer->name ?? null,
            'user_name'         => $conversation->customer->username ?? null,
            'phone_number'      => $conversation->customer->phone ?? null,
            'email'             => $conversation->customer->email ?? null,
            'is_registered'     => 'Yes', // hardcoded as before
            'is_authenticated'  => 'Yes', // hardcoded as before
            // Load all messages via MessageResource
            'messages'          => MessageResource::collection($conversation->messages),
        ];
    }

    private function resolveStatus($conversation): string
    {
        if (!empty($conversation->end_at)) {
            if (!empty($conversation->endedBy)) {
                return "EndChatFromAgent (" . optional($conversation->endedBy)->name . ")";
            }
            return 'EndChat';
        }
        return 'Active';
    }
}
