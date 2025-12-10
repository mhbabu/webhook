<?php

namespace App\Exports\Report;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Collection;

class ConversationReportExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Query for export with necessary counts and relations
     */
    public function query()
    {
        return Conversation::whereDate('created_at', '>=', $this->data['start_date'])
            ->whereDate('created_at', '<=', $this->data['end_date'])
            ->withCount([
                'messages as agent_message_count' => fn($q) => $q->where('sender_type', User::class),
                'messages as customer_message_count' => fn($q) => $q->where('sender_type', Customer::class),
                'systemMessages as system_message_count'
            ])
            ->with([
                'messages' => fn($q) => $q->select('id', 'conversation_id', 'sender_type', 'created_at')->orderBy('created_at'),
                'customer:id,name,phone,email',
                'agent:id,name',
                'wrapUp:id,name',
                'endedBy:id,name',
                'rating:id,conversation_id,rating,feedback',
                'lastMessage:id,conversation_id,content,created_at'
            ]);
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function headings(): array
    {
        // SrNo SessionID ChannelSource InteractionType AgentName 
        //LoginId CustomerName Number CustomerEmail DisconnectionStatus
        //SessionStartTime AgentRouteTime FirstResponseTime SessionEndTime ChatEndTime
        // InteractionDuration QueueTime AvgResponseTime Remarks CustomerRating(Disposition SubDisposition)
        // CustomerFeedback SystemMsgCount CustomerMsgCount AgentMsgCount AvgAgentRespTime

        return [
            'SrNo', //1
            'SessionID', //2
            'ChannelSource', //3
            'InteractionType', //4
            'AgentName', //5
            'LoginId', //6
            'CustomerName', //7
            'Number', //8
            'CustomerEmail', //9
            'DisconnectionStatus', //10
            'SessionStartTime', //11
            'AgentRouteTime', //12
            'FirstResponseTime', //13
            'SessionEndTime', //14
            'ChatEndTime', //15
            'InteractionDuration', //16
            'QueueTime', //17
            // 'AvgResponseTime', //18
            'Remarks', //19
            'CustomerRating', //20
            'CustomerFeedback', //21
            'SystemMsgCount', //22
            'CustomerMsgCount', //23
            'AgentMsgCount', //24
            // 'AvgAgentRespTime' //25
        ];
    }

    public function map($conversation): array
    {
        static $counter = 0;
        $counter++;

        return [
            $counter, //1SrNo
            $conversation->trace_id, //2 SessionID
            'SAMSUNG', //3 ChannelSource
            $conversation->platform, //4 InteractionType
            $conversation->agent->name ?? null, //5 AgentName
            $conversation->agent->name ?? null, //6 LoginId
            $conversation->customer->name ?? null, //7 CustomerName
            $conversation->customer->phone ?? null, //8 Number
            $conversation->customer->email ?? null, //9 CustomerEmail
            $conversation->wrapUp->name ?? null, //10 DisconnectionStatus
            $conversation->first_message_at->toDateTimeString ?? null, //11 SessionStartTime
            $conversation->first_message_at->toDateTimeString ?? null, //12 AgentRouteTime
            $conversation->first_response_at->toDateTimeString ?? null, //13 FirstResponseTime
            !empty($conversation->end_at) ? $conversation->end_at->format('Y-m-d H:i:s') : null, //14 SessionEndTime
            !empty($conversation->end_at) ? $conversation->end_at->format('Y-m-d H:i:s') : null, //15 ChatEndTime
            $conversation->first_message_at && $conversation->last_message_at ? gmdate('H:i:s', $conversation->last_message_at->diffInSeconds($conversation->first_message_at)) : '00:00:00', // 16 InteractionDuration
            $conversation->in_queue_at && $conversation->agent_assigned_at ? gmdate('H:i:s', $conversation->agent_assigned_at->diffInSeconds($conversation->in_queue_at)) : '00:00:00', //17 QueueTime
            // $this->calculateAverageResponseTime($conversation->messages), // 18 AvgResponseTime
            $conversation->wrapUp->name ?? null, //19 Remarks
            $conversation->rating->rating_value ?? '0', //20 CustomerRating
            $conversation->option_label ?? null, //21 CustomerFeedback
            $conversation->system_message_count ?? '0', //22 SystemMsgCount
            $conversation->customer_message_count ?? '0', //23 CustomerMsgCount
            $conversation->agent_message_count ?? '0', //24 AgentMsgCount
        ];
    }

    /**
     * Calculate average agent response time (in seconds) for a conversation
     */
    protected function calculateAverageResponseTime(Collection $messages): float
    {
        $messages = $messages->sortBy('created_at')->values();

        $totalDiff = 0;
        $responseCount = 0;

        for ($i = 1; $i < $messages->count(); $i++) {
            $prev = $messages[$i - 1];
            $curr = $messages[$i];

            if ($prev->sender_type === Customer::class && $curr->sender_type === User::class) {
                $totalDiff += $curr->created_at->diffInSeconds($prev->created_at);
                $responseCount++;
            }
        }

        return $responseCount > 0 ? round($totalDiff / $responseCount, 2) : 0;
    }
}
