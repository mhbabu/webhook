<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SocketIncomingMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }

    public function broadcastOn()
    {
        $platform = strtolower($this->data['platform']);
        $agentId  = $this->data['agentId'];

        return new PrivateChannel("platform.{$platform}.{$agentId}");
        // return new Channel("platform.{$platform}.{$agentId}");

    }
}
