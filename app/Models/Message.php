<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['conversation_id', 'direction', 'message_id', 'sender_id', 'receiver_id', 'type', 'content', 'sent_at'];
    
    public function conversation() { 
        return $this->belongsTo(Conversation::class); 
    }
    
    public function attachments() { 
        return $this->hasMany(Attachment::class); 
    }
}
