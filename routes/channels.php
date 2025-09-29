<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('private-user.{userId}', function (User $user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});


Broadcast::channel('platform.{source}.{agentId}', function ($user, $source, $agentId) {    
    return (int) $user->id === (int) $agentId;
});