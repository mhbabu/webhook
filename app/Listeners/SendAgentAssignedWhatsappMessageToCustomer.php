<?php

namespace App\Listeners;

use App\Events\AgentAssignedToConversationEvent;
use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\ConversationTemplateMessage;
use App\Services\Platforms\WhatsAppService;
use Illuminate\Support\Facades\Log;

class SendAgentAssignedWhatsappMessageToCustomer
{
    protected $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    public function handle(AgentAssignedToConversationEvent $event)
    {
        $conversation = $event->conversation;
        $agent        = $event->agent;
        $messageId    = $event->messageId;

        $customer = Customer::find($conversation->customer_id);

        if (! $customer) {
            Log::error("Customer not found", ['conversation_id' => $conversation->id]);
            return;
        }

        $template = MessageTemplate::where('type', 'agent_assigned')->first();
        $content  = $template ? "*" . $agent->name . "* " . $template->content : "*" . $agent->name . "* joined the session. Thank you for contacting us.";

        // Prevent duplicate messages
        $exists = ConversationTemplateMessage::where('conversation_id', $conversation->id)->where('template_id', $template?->id)->exists();

        if ($exists) {
            Log::info("Agent assigned message already sent. Skipping...", ['conversation_id' => $conversation->id]);
            return;
        }

        // Insert only ONCE
        $record = ConversationTemplateMessage::create([
            'conversation_id' => $conversation->id,
            'template_id'     => $template?->id,
            'customer_id'     => $customer->id,
            'message_id'      => $messageId,
            'content'         => $content
        ]);

        try {
            $this->whatsAppService->sendTextMessage($customer->phone, $content);
            $record->update(['is_sent' => true]);
        } catch (\Exception $e) {
            $record->update(['error' => $e->getMessage()]);
        }
    }
}
