<?php

namespace App\Listeners;

use App\Events\SendSystemConfigureMessageEvent;
use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\ConversationTemplateMessage;
use App\Services\Platforms\WhatsAppService;
use Illuminate\Support\Facades\Log;

class SystemConfigureMessageSendListener
{
    protected $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    public function handle(SendSystemConfigureMessageEvent $event)
    {
        $conversation = $event->conversation;
        $agent        = $event->agent;
        $messageId    = $event->messageId;
        $type         = $event->type;

        // Find the customer associated with the conversation
        $customer = Customer::find($conversation->customer_id);

        if (!$customer) {
            Log::error("Customer not found", ['conversation_id' => $conversation->id]);
            return;
        }

        // Fetch the message template based on the type
        $template = MessageTemplate::where('type', $type)->first();

        // Get the message content dynamically
        $content = $this->getMessageContent($type, $agent, $template);

        // Prevent duplicate messages
        $exists = ConversationTemplateMessage::where('conversation_id', $conversation->id)
            ->where('template_id', $template?->id)
            ->where('message_id', $messageId)
            ->exists();

        if ($exists) {
            Log::info("Message already sent. Skipping...", ['conversation_id' => $conversation->id]);
            return;
        }

        // Insert the message record into the database
        $record = ConversationTemplateMessage::create([
            'conversation_id' => $conversation->id,
            'template_id'     => $template?->id,
            'customer_id'     => $customer->id,
            'message_id'      => $messageId,
            'content'         => $content
        ]);

        // Handle message types dynamically (cchat for feedback)
        if ($type === 'cchat') {
            $this->sendFeedbackOptions($customer, $template);
        }else {
            try {
                // Send standard message (text or template)
                $this->whatsAppService->sendTextMessage($customer->phone, $content);
                $record->update(['is_sent' => true]);
            } catch (\Exception $e) {
                $record->update(['error' => $e->getMessage()]);
                Log::error("Failed to send message: " . $e->getMessage(), ['conversation_id' => $conversation->id]);
            }
        }
    }

    // Generate message content dynamically based on the type
    protected function getMessageContent($type, $agent, $template)
    {
        if ($template) {
            // If template exists, use it
            if ($type === 'agent_assigned') {
                return "*" . $agent->name . "* " . $template->content;
            }
            return $template->content;
        } else {
            // If template doesn't exist, fall back to default messages based on type
            return $this->getFallbackMessage($type, $agent);
        }
    }

    // Fallback message content based on the message type
    protected function getFallbackMessage($type, $agent = null)
    {
        // Define fallback messages dynamically for various types
        $fallbackMessages = [
            'agent_assigned'   => $agent ? "*" . $agent->name . "* has joined the session. Thank you for contacting us. I will be happy to assist you." : "An agent has joined the session. Thank you for contacting us.",
            'alert'            => "Hello, are you there?",
            'second_alert'     => "We haven’t heard from you in a while. Please respond to continue the conversation.",
            'third_alert'      => "Thank you for contacting us. Have a great day, and feel free to reach out anytime!",
            'end_chat'         => "Thank you for your time. We appreciate your feedback.",
            'cchat'            => "Kindly rate your experience with our support during this conversation.",
            'after_feedback'   => "Thank you for your feedback. We value your input.",
            'end_cchat_alert'  => "We are waiting for your valuable feedback.",
        ];

        return $fallbackMessages[$type] ?? "Thank you for contacting us. We appreciate your patience.";
    }

    // Send feedback options to the customer (interactive list message for `cchat`)
    protected function sendFeedbackOptions($customer, $template)
    {
        $options = collect($template->options)->map(function ($option) {
            return [
                'label' => $option['label'],
                'value' => $option['value']
            ];
        })->toArray();

        try {
            // Send interactive list message for feedback
            $this->whatsAppService->sendInteractiveMessage(
                $customer->phone,
                'Kindly rate your experience with our specialist during this conversation.',
                $options
            );
        } catch (\Exception $e) {
            Log::error("Failed to send feedback options: " . $e->getMessage(), ['customer' => $customer->id]);
        }
    }
}


