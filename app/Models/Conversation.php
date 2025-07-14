<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['user_id', 'platform_id', 'agent_id', 'external_conversation_id'];
    
    public function messages() { 
        return $this->hasMany(Message::class); 
    }

    public function agent() { 
        return $this->belongsTo(User::class, 'agent_id'); 
    }

    public function user() { 
        return $this->belongsTo(User::class, 'user_id'); 
    }
}
