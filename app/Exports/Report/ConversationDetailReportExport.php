<?php

namespace App\Exports\Report;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ConversationDetailReportExport implements FromCollection, WithHeadings, WithMapping
{
    protected string $traceId;
    protected static int $sl = 1;

    public function __construct(string $traceId)
    {
        $this->traceId = $traceId;
    }

    /**
     * Fetch conversation messages
     */
    public function collection()
    {
        return DB::table('messages')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->leftJoin('customers', 'conversations.customer_id', '=', 'customers.id')
            ->leftJoin('users as agents', 'conversations.agent_id', '=', 'agents.id')
            ->leftJoin('users as endedBy', 'conversations.ended_by', '=', 'endedBy.id')
            ->where('conversations.trace_id', $this->traceId)
            ->select(
                // Message
                'messages.delivered_at',
                'messages.content',
                'messages.type',
                'messages.direction',
                'messages.sender_type',
                'messages.sender_id',

                // Conversation
                'conversations.trace_id',
                'conversations.agent_assigned_at',
                'conversations.first_response_at as agent_first_response_at',
                'conversations.end_at',
                'conversations.platform',

                // Customer
                'customers.username',
                'customers.name as customer_name',
                'customers.phone',
                'customers.email',

                // Agent
                'agents.name as agent_name',

                // Ended by
                'endedBy.name as ended_by_name'
            )
            ->orderBy('messages.delivered_at', 'asc')
            ->get();
    }

    /**
     * Excel Headings
     */
    public function headings(): array
    {
        return [
            'SL',
            'SessionId',
            'SessionStartTime',
            'AgentRouteTime',
            'SessionEndTime',
            'MessageTime',
            'MessageType',
            'Message',
            'Sender',
            'Receiver',
            'UserName',
            'Channel',
            'InteractionType',
            'Status',
            'AgentName',
            "Customer's Name",
            'Phone Number',
            'Email',
            'IsRegistered',
            'IsAuthenticated',
        ];
    }

    /**
     * Map each row
     */
    public function map($row): array
    {
        // Determine sender
        $sender = match($row->sender_type) {
            \App\Models\User::class => 'Agent',
            \App\Models\Customer::class => 'Customer',
            default => 'Unknown',
        };

        // Determine receiver
        $receiver = match($row->sender_type) {
            \App\Models\User::class => $row->customer_name ?? 'Customer',
            \App\Models\Customer::class => $row->agent_name ?? 'Agent',
            default => 'Unknown',
        };

        return [
            self::$sl++,
            $row->trace_id,
            $this->formatDate($row->agent_assigned_at),
            $this->formatDate($row->agent_first_response_at),
            $this->formatDate($row->end_at),
            $this->formatDate($row->delivered_at),
            $row->type,
            $row->content,
            $sender,
            $receiver,
            $row->username,
            $row->platform ?? null,
            $row->direction,
            $this->resolveStatus($row),
            $row->agent_name ?? null,
            $row->customer_name ?? null,
            $row->phone ?? null,
            $row->email ?? null,
            'Yes', // IsRegistered
            'Yes', // IsAuthenticated
        ];
    }

    /**
     * Format date
     */
    private function formatDate($value): ?string
    {
        return $value ? date('Y-m-d H:i:s', strtotime($value)) : null;
    }

    /**
     * Resolve conversation status
     */
    private function resolveStatus($row): string
    {
        if (!empty($row->end_at)) {
            if (!empty($row->ended_by_name)) {
                return "EndChatFromAgent ({$row->ended_by_name})";
            }
            return 'EndChat';
        }
        return 'Active';
    }
}
