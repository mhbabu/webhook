<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Platforms\WhatsAppService;
use App\Models\MessageTemplate;
use App\Models\Conversation;
use App\Models\ConversationTemplateMessage;
use Carbon\Carbon;
use App\Models\User;

class CustomerInactivityChecker extends Command
{
    protected $signature   = 'conversations:customer-inactivity';
    protected $description = 'Sends customer inactivity message after 2 minutes of no response';

    protected $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        parent::__construct();
        $this->whatsAppService = $whatsAppService;
    }

    public function handle()
    {
        $now = Carbon::now();

        // Get message templates
        $alertTemplate       = MessageTemplate::where('type', 'alert')->where('is_active', true)->first();
        $secondAlertTemplate = MessageTemplate::where('type', 'second_alert')->where('is_active', true)->first();
        $thirdAlertTemplate  = MessageTemplate::where('type', 'third_alert')->where('is_active', true)->first();

        if (!$alertTemplate || !$secondAlertTemplate || !$thirdAlertTemplate) {
            $this->error('Required message templates not found.');
            return;
        }

        // Get active conversations where the last message's delivery_at is at least 2-4 minutes ago
        $conversations = Conversation::whereNull('end_at')
            ->whereNotNull('last_message_id')
            ->whereNotNull('customer_id')
            ->whereNotNull('agent_id')
            ->whereHas('lastMessage', function ($query) use ($now) {
                $query->where('sender_type', User::class)
                    ->where('delivery_at', '<=', $now->subMinutes(config('alert-message.minutes_limit')));  // Filter for conversations where last message is at least 4 minutes ago
            })
            ->with('lastMessage')
            ->get();

        foreach ($conversations as $conversation) {
            $lastMessage = $conversation->lastMessage;

            // Skip if the last message was not sent by an agent (User::class)
            if (!$lastMessage || $lastMessage->sender_type !== User::class) {
                continue;
            }

            // Calculate the time difference since the agent's last message
            $timeSinceLastMessage = $lastMessage->delivery_at->diffInMinutes($now);

            // If 2 to 3 minutes have passed, send the first alert
            if ($timeSinceLastMessage >= config('alert-message.inactivity_alert_minutes') && $timeSinceLastMessage < config('alert-message.second_alert_minutes')) {
                $this->sendFirstAlert($conversation, $alertTemplate);
            }

            // If no response after the first alert, and it's been 3 to 4 minutes since the agent's last message, send second and third alerts
            if ($timeSinceLastMessage >= config('alert-message.second_alert_minutes') && $timeSinceLastMessage < config('alert-message.minutes_limit')) {
                $this->sendSecondAndThirdAlert($conversation, $secondAlertTemplate, $thirdAlertTemplate);
            }
        }
    }

    /**
     * Send first alert (inactivity message) to customer.
     */
    protected function sendFirstAlert($conversation, $alertTemplate)
    {
        // Check if the first alert was already sent for this conversation and ensure the last message is from the agent
        $alertAlreadySent = ConversationTemplateMessage::where('conversation_id', $conversation->id)
            ->where('template_id', $alertTemplate->id)
            ->where('message_id', $conversation->lastMessage->id)  // Check if the message_id is the same as the last message
            ->exists();

        if ($alertAlreadySent) {
            return; // Skip if the first alert was already sent for this message
        }

        // Send first alert
        $this->sendMessageToCustomer($conversation, $alertTemplate);

        // Track the first alert message as sent
        $this->trackSentMessage($conversation, $alertTemplate);
    }

    /**
     * Send second and third alerts (inactivity messages) to customer.
     */
    protected function sendSecondAndThirdAlert($conversation, $secondAlertTemplate, $thirdAlertTemplate)
    {
        // Check if the second alert was already sent for this conversation and ensure the last message is from the agent
        $secondAlertAlreadySent = ConversationTemplateMessage::where('conversation_id', $conversation->id)
            ->where('template_id', $secondAlertTemplate->id)
            ->where('message_id', $conversation->lastMessage->id)  // Check if the message_id is the same as the last message
            ->exists();

        // If the second alert was not sent yet, send it
        if (!$secondAlertAlreadySent) {
            $this->sendMessageToCustomer($conversation, $secondAlertTemplate);
            $this->trackSentMessage($conversation, $secondAlertTemplate);
        }

        // Send third alert (this is sent after the second alert)
        $this->sendMessageToCustomer($conversation, $thirdAlertTemplate);
        $this->trackSentMessage($conversation, $thirdAlertTemplate);
    }

    /**
     * Send a message to the customer through WhatsAppService.
     */
    protected function sendMessageToCustomer($conversation, $template)
    {
        // Get the customer's phone number from the conversation
        $customerPhone = $conversation->customer->phone;

        // Use WhatsAppService to send the message to the customer
        $this->whatsAppService->sendTextMessage($customerPhone, $template->content);

        // Log the action for tracking purposes
        $this->info("Sent '{$template->type}' message to customer — Conversation ID {$conversation->id}");

        // Track that this message template has been sent for this conversation
        $this->trackSentMessage($conversation, $template);
    }

    /**
     * Track that the message template was sent for the conversation.
     */
    protected function trackSentMessage($conversation, $template)
    {
        // Insert a record into the `conversation_template_messages` table to track the message sent
        ConversationTemplateMessage::create([
            'conversation_id' => $conversation->id,
            'template_id'     => $template->id,
            'customer_id'     => $conversation->customer_id,
            'content'         => $template->content,
            'message_id'      => $conversation->lastMessage->id,  // Store the message_id of the last sent message
        ]);

        $this->info("Tracked message template '{$template->type}' for Conversation ID {$conversation->id}");
    }
}
