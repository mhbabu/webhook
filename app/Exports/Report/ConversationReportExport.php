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
                'messages' => fn($q) => $q->select('id', 'conversation_id', 'sender_type', 'created_at', 'delivered_at')->orderBy('created_at'),
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
            'AvgResponseTime', //18
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
            $this->calculateAverageResponseTimeInSeconds($conversation->messages), // 18 AvgResponseTime
            $conversation->wrapUp->name ?? null, //19 Remarks
            $conversation->rating->rating_value ?? '0', //20 CustomerRating
            $conversation->option_label ?? null, //21 CustomerFeedback
            $conversation->system_message_count ?? '0', //22 SystemMsgCount
            $conversation->customer_message_count ?? '0', //23 CustomerMsgCount
            $conversation->agent_message_count ?? '0', //24 AgentMsgCount
        ];
    }

    /**
     * Calculate the average agent response time for a single conversation in seconds.
     *
     * This method computes how quickly an agent responds to customer messages.
     * Only counts actual responses:
     *   1. Agent messages that come after at least one customer message.
     *   2. Multiple consecutive customer messages before an agent reply are treated as a single waiting period.
     *   3. Agent messages without a preceding customer message are ignored.
     *   4. System messages or messages from other agents are ignored.
     *
     * @param Collection $messages A collection of messages in the conversation.
     * @return float Average response time in seconds. Returns 0 if there are no valid agent responses.
     */
    protected function calculateAverageResponseTimeInSeconds($messages)
    {
        // Sort messages by delivered_at and reset keys
        $messages = $messages->sortBy('delivered_at')->values();

        $waitingCustomerTime = null; // Time of first customer message waiting for reply
        $totalSeconds = 0;           // Total response time in seconds
        $responseCount = 0;          // Count of actual agent responses

        foreach ($messages as $message) {

            // Skip messages without delivered_at
            if (!$message->delivered_at) {
                continue;
            }

            // Customer message → start waiting if not already waiting
            if ($message->sender_type === Customer::class) {
                if ($waitingCustomerTime === null) {
                    $waitingCustomerTime = $message->delivered_at;
                }
            }

            // Agent message → counts as a response if waiting
            if ($message->sender_type === User::class && $waitingCustomerTime !== null) {

                // Calculate difference in seconds
                $diff = $message->delivered_at->diffInSeconds($waitingCustomerTime);
                $totalSeconds += abs($diff);

                $responseCount++;
                $waitingCustomerTime = null; // Reset for next customer → agent pair
            }
        }

        if ($responseCount === 0) {
            return "00:00";
        }

        // Average in seconds
        $averageSeconds = round($totalSeconds / $responseCount);

        // Convert to minutes:seconds
        $minutes = floor($averageSeconds / 60);
        $seconds = $averageSeconds % 60;

        return sprintf("%02d:%02d", $minutes, $seconds);
    }
}
