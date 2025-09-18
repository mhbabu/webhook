<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['user_id', 'platform_id', 'agent_id', 'external_conversation_id', 'ended_by', 'wrap_up_conversation_id', 'end_at'];

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lastMessage()
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }
     
    public function endWrapup()
    {
        return $this->belongsTo(WrapUpConversation::class, 'wrap_id', 'id');
    }
}
