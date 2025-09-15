<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});


Broadcast::channel('platform.{source}.{agentId}', function ($user, $agentId) {    
    info("Authorizing user ID {$user->id} for agentId {$agentId}");
    return (int) $user->id === (int) $agentId;
});

Broadcast::channel('ack.incoming', function ($user, $id) {
    return true;
});