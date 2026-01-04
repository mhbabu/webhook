<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Platforms\WhatsAppService;
use App\Models\MessageTemplate;
use App\Models\Conversation;
use App\Models\ConversationTemplateMessage;
use Carbon\Carbon;

class EndChatAlertChecker extends Command
{
    protected $signature = 'conversations:feedback-alert';
    protected $description = 'Send end_cchat_alert messages for conversations with no feedback';

    protected $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        parent::__construct();
        $this->whatsAppService = $whatsAppService;
    }

    public function handle()
    {
        $now            = now();
        info("Running EndChatAlertChecker... {$now}");
        $fiveMinutesAgo = $now->copy()->subMinutes(5);

        // Fetch end_cchat_alert template
        $alertTemplate = MessageTemplate::where('type', 'end_cchat_alert')->first();

        if (!$alertTemplate) {
            $this->error('end_cchat_alert template not found.');
            return;
        }

        // Step 1: Get conversations matching criteria
        $conversations = Conversation::whereNotNull('end_at')
            ->whereNotNull('last_message_id')
            ->whereNotNull('customer_id')
            ->whereNotNull('agent_id')
            ->where('is_feedback_sent', 0)
            ->whereBetween('end_at', [$fiveMinutesAgo, $now])
            ->get();

        if (count($conversations) == 0) {
            $this->info('No conversations found for end_cchat_alert.');
            return 0; // ✅ Success
        }
        foreach ($conversations as $conversation) {
            // Step 2: Find latest cchat message
            $cchatMessage = ConversationTemplateMessage::where('conversation_id', $conversation->id)
                ->whereHas('template', fn($q) => $q->where('type', 'cchat'))
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$cchatMessage) {
                continue; // Skip if no cchat
            }

            // Step 3: Check cchat sent between subMinutes(3) and subMinutes(2)
            $cchatTime = $cchatMessage->created_at;
            if ($cchatTime->between(now()->subMinutes(3), now()->subMinutes(2))) {
                // Step 4: Check if end_cchat_alert already sent
                $alertExists = ConversationTemplateMessage::where('conversation_id', $conversation->id)
                    ->where('template_id', $alertTemplate->id)
                    ->where('message_id', $cchatMessage->id) // linked to cchat
                    ->exists();

                if ($alertExists) {
                    continue; // Already sent
                }

                // Send the alert
                try {
                    $this->whatsAppService->sendTextMessage($conversation->customer->phone, $alertTemplate->content);

                    // Record the alert
                    ConversationTemplateMessage::create([
                        'conversation_id' => $conversation->id,
                        'template_id'     => $alertTemplate->id,
                        'customer_id'     => $conversation->customer_id,
                        'content'         => $alertTemplate->content,
                        'message_id'      => $cchatMessage->id, // link to cchat
                    ]);

                    $this->info("Sent end_cchat_alert for Conversation ID {$conversation->id}");
                } catch (\Exception $e) {
                    $this->error("Failed to send alert for Conversation ID {$conversation->id}: " . $e->getMessage());
                }
            }
        }
    }
}
