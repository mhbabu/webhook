<?php

use App\Models\AgentPlatformRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessIncomingMessageJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $platform;
    protected $payload;

    public function __construct($platform, $payload)
    {
        $this->platform = $platform;
        $this->payload = $payload;
    }

    public function handle()
    {
        $platform = Platform::where('name', $this->platform)->firstOrFail();

        // Extract user info from payload (depends on platform)
        $externalUserId = $this->extractUserId($this->payload);

        // Find or create user
        $user = User::firstOrCreate([
            'external_id' => $externalUserId,
            'platform' => $this->platform,
        ], [
            'name' => $this->extractUserName($this->payload),
            'phone' => $this->extractUserPhone($this->payload),
        ]);

        // Check for existing open conversation for this user & platform
        $conversation = Conversation::where('user_id', $user->id)
            ->where('platform_id', $platform->id)
            ->whereNull('end_at') // Add end_at if you want to close conversations
            ->first();

        if (!$conversation) {
            // Find an eligible agent to assign conversation

            $agentRole = AgentPlatformRole::where('platform_id', $platform->id)
                ->whereIn('status', ['OCCUPIED', 'BUSY'])
                ->whereColumn('current_load', '<', 'max_limit')
                ->orderBy('current_load')
                ->first();

            if (!$agentRole) {
                // fallback to OFFLINE or BREAK agents with lowest load
                $agentRole = AgentPlatformRole::where('platform_id', $platform->id)
                    ->whereIn('status', ['OFFLINE', 'BREAK'])
                    ->orderBy('current_load')
                    ->first();
            }

            if (!$agentRole) {
                // No agent available, handle accordingly (e.g. auto-reply or queue for later)
                return;
            }

            $agentRole->increment('current_load');

            $conversation = Conversation::create([
                'user_id' => $user->id,
                'platform_id' => $platform->id,
                'agent_id' => $agentRole->agent_id,
                'external_conversation_id' => $this->extractExternalConversationId($this->payload),
            ]);
        }

        // Save incoming message
        Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'incoming',
            'content' => $this->extractMessageContent($this->payload),
            'attachments' => $this->extractAttachments($this->payload),
            'sent_at' => $this->extractMessageTimestamp($this->payload),
            'message_id' => $this->extractMessageId($this->payload),
            'sender_id' => $externalUserId,
        ]);

        // Notify agent, update UI, etc.
    }

    // Platform-specific extraction methods here
    protected function extractUserId($payload) { /* ... */ }
    protected function extractUserName($payload) { /* ... */ }
    protected function extractUserPhone($payload) { /* ... */ }
    protected function extractExternalConversationId($payload) { /* ... */ }
    protected function extractMessageContent($payload) { /* ... */ }
    protected function extractAttachments($payload) { /* ... */ }
    protected function extractMessageTimestamp($payload) { /* ... */ }
    protected function extractMessageId($payload) { /* ... */ }
}

