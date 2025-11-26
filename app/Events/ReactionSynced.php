<?php

namespace App\Events;

use App\Models\Reaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReactionSynced
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Reaction $reaction;

    public function __construct(Reaction $reaction)
    {
        $this->reaction = $reaction;
    }
}
