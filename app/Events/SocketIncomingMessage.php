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
    public $channelData;

    public function __construct($data, $channelData)
    {
        $this->data = $data;
        $this->channelData = $channelData;
    }

    public function broadcastWith(): array
    {
        // info('Broadcasting on channel with data: ' . json_encode(['data' => $this->data]));
        return ['data' => $this->data];
    }

    public function broadcastOn()
    {
        $platform = strtolower($this->channelData['platform']);
        $agentId  = $this->channelData['agentId'];
        return new PrivateChannel("platform.{$platform}.{$agentId}");
    }
}
