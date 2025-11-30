<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentAssignedToConversationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $conversation;
    public $agent;

    public function __construct(Conversation $conversation, User $agent)
    {
        $this->conversation = $conversation;
        $this->agent        = $agent;
    }
}
