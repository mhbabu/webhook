<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Platforms\WhatsAppService;
use App\Models\MessageTemplate;
use App\Models\Conversation;
use App\Models\ConversationTemplateMessage;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Facades\Schema;


class CustomerInactivityChecker extends Command
{
    protected $signature   = 'conversations:customer-inactivity';
    protected $description = 'Sends customer inactivity messages after 2 minutes of no response';

    protected $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        parent::__construct();
        $this->whatsAppService = $whatsAppService;
    }

    public function handle()
    {
        $now = now();
        info("Running CustomerInactivityChecker at {$now}");
        // Get active message templates
        $alertTemplate       = MessageTemplate::where('type', 'alert')->where('is_active', true)->first();
        $secondAlertTemplate = MessageTemplate::where('type', 'second_alert')->where('is_active', true)->first();
        $thirdAlertTemplate  = MessageTemplate::where('type', 'third_alert')->where('is_active', true)->first();

        if (!$alertTemplate || !$secondAlertTemplate || !$thirdAlertTemplate) {
            $this->error('Required message templates not found.');
            return;
        }

        // Get conversations with last agent message delivered 2–3 minutes ago
        $conversations = Conversation::with('lastMessage')
            ->whereNull('end_at')
            ->whereNotNull('last_message_id')
            ->whereNotNull('customer_id')
            ->whereNotNull('agent_id')
            ->whereHas('lastMessage', function ($query) {
                $query->where('sender_type', User::class)
                    ->whereRaw('TIMESTAMPDIFF(MINUTE, delivered_at, NOW()) BETWEEN 2 AND 3');
            })
            ->get();

        if (count($conversations) == 0) {
            $this->info('No conversations found with customer inactivity.');
            return 0; // ✅ Success
        }

        foreach ($conversations as $conversation) {
            $lastMessage = $conversation->lastMessage;

            if (!$lastMessage || $lastMessage->sender_type !== User::class) {
                continue;
            }

            $timeSinceLastMessage = $lastMessage->delivered_at->diffInMinutes(now());

            // First alert: 2–3 minutes of inactivity
            if ($timeSinceLastMessage >= config('alert-message.inactivity_alert_minutes') && $timeSinceLastMessage < config('alert-message.second_alert_minutes')) {
                $this->sendAlertOnce($conversation, $alertTemplate);
            }

            // Second + third alerts: 3–4 minutes of inactivity
            if ($timeSinceLastMessage >= config('alert-message.second_alert_minutes') && $timeSinceLastMessage < config('alert-message.minutes_limit')) {
                $this->sendAlertOnce($conversation, $secondAlertTemplate);
                $this->sendAlertOnce($conversation, $thirdAlertTemplate);
            }
        }

        return 0; // ✅ Success
    }

    /**
     * Sends an alert message if not already sent for the last message.
     */
    protected function sendAlertOnce($conversation, $template)
    {
        $lastMessageId = $conversation->lastMessage->id;

        $alreadySent = ConversationTemplateMessage::where('conversation_id', $conversation->id)
            ->where('template_id', $template->id)
            ->where('message_id', $lastMessageId)
            ->exists();

        if ($alreadySent) {
            return; // Skip if already sent
        }

        $this->sendMessageToCustomer($conversation, $template);
    }

    /**
     * Send a message to the customer through WhatsAppService and track it.
     */
    protected function sendMessageToCustomer($conversation, $template)
    {
        $customerPhone = $conversation->customer->phone;

        $this->whatsAppService->sendTextMessage($customerPhone, $template->content);

        // Track the message
        ConversationTemplateMessage::create([
            'conversation_id' => $conversation->id,
            'template_id'     => $template->id,
            'customer_id'     => $conversation->customer_id,
            'content'         => $template->content,
            'message_id'      => $conversation->lastMessage->id,
        ]);

        $this->info("Sent '{$template->type}' message to customer — Conversation ID {$conversation->id}");
    }
}
