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

class SendSystemConfigureMessageEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $conversation;
    public $agent;
    public $messageId;
    public $type;

    public function __construct(Conversation $conversation, User $agent, $messageId, $type)
    {
        $this->conversation = $conversation;
        $this->agent        = $agent;
        $this->messageId    = $messageId;
        $this->type         = $type;
    }
}
