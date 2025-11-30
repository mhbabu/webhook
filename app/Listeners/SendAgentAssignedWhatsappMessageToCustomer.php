<?php

namespace App\Listeners;

use App\Events\AgentAssignedToConversationEvent;
use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\ConversationTemplateMessage;
use App\Services\Platforms\WhatsAppService;
use Illuminate\Support\Facades\DB;
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

        // Get customer
        $customer = Customer::find($conversation->customer_id);
        if (! $customer) {
            Log::error("Customer not found for conversation", ['conversation_id' => $conversation->id]);
            return;
        }

        // Get message template
        $messageTemplate = MessageTemplate::where('type', 'agent_assigned')->first();
        $messageContent  = $messageTemplate  ? '*' . $agent->name . "* " . $messageTemplate->content : '*' . $agent->name . "* joined the session. Thank you for contacting us. I will be glad to assist you.";

        // Create a record in conversation_template_messages
        $templateRecord = ConversationTemplateMessage::create([
            'conversation_id' => $conversation->id,
            'template_id'     => $messageTemplate?->id,
            'customer_id'     => $customer->id,
            'content'         => $messageContent,
            'is_sent'         => false,
        ]);

        try {
            // Send message via WhatsAppService
            $this->whatsAppService->sendTextMessage($customer->phone, $messageContent);

            // Mark template message as sent
            $templateRecord->update(['is_sent' => true]);

            Log::info("Agent-assigned WhatsApp message sent successfully", [
                'conversation_id' => $conversation->id,
                'agent_id'        => $agent->id,
                'customer_phone'  => $customer->phone
            ]);
        } catch (\Exception $e) {
            // Update the record with error message
            $templateRecord->update(['error' => $e->getMessage()]);

            Log::error("Failed to send agent-assigned WhatsApp message", [
                'conversation_id' => $conversation->id,
                'agent_id'        => $agent->id,
                'error'           => $e->getMessage()
            ]);
        }
    }
}
