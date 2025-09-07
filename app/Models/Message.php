<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Message extends Model
{
    protected $fillable = ['conversation_id', 'direction', 'message_id', 'sender_id', 'sender_type', 'receiver_id', 'receiver_type', 'content', 'sent_at'];

    public function conversation() {
        return $this->belongsTo(Conversation::class);
    }
    
    public function attachments() { 
        return $this->hasMany(MessageAttachment::class); 
    }

     // Polymorphic relationship for sender
    public function sender(): MorphTo {
        return $this->morphTo();
    }

    // Optional polymorphic receiver
    public function receiver(): MorphTo {
        return $this->morphTo();
    }
}
