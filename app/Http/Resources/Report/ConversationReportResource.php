<?php

namespace App\Http\Resources\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'customer'  => $this->customer ? [
                'name'  => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
            ] : null,

            'agent' => $this->agent ? [
                'name'        => $this->agent->name,
                'email'       => $this->agent->email,
                'employee_id' => $this->agent->employee_id,

            ] : null,

            'last_message' => $this->lastMessage ? [
                'content'  => $this->lastMessage->content ?? null,
                'sent_at'  => $this->lastMessage->created_at?->format('Y-m-d H:i:s'),
            ] : null,

            'wrap_up'            => $this->wrapUp->name ?? null,
            'platform'           => $this->platform,
            'trace_id'           => $this->trace_id,
            'started_at'         => $this->started_at?->format('Y-m-d H:i:s'),
            'end_at'             => $this->end_at?->format('Y-m-d H:i:s'),
            'ended_by'           => $this->endedBy?->name,
            'in_queue_at'        => $this->in_queue_at?->format('Y-m-d H:i:s'),
            'first_message_at'   => $this->first_message_at?->format('Y-m-d H:i:s'),
            'last_message_at'    => $this->last_message_at?->format('Y-m-d H:i:s'),
            'agent_assigned_at'  => $this->agent_assigned_at?->format('Y-m-d H:i:s'),
            'is_feedback_sent'   => (bool) $this->is_feedback_sent,
            'created_at'         => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'         => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
