<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});


Broadcast::channel('{source}.{agentId}', function ($user, $id) {    
    return (int) $user->id === (int) $id;
});

Broadcast::channel('ack.incoming', function ($user, $id) {
    return true;
});