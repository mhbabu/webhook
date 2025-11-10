<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = ['conversation_id', 'sender_id', 'sender_type', 'receiver_id', 'receiver_type', 'type', 'subject', 'content', 'html_content', 'direction', 'read_at', 'read_by', 'platform_message_id', 'parent_id', 'delivered_at'];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    // polymorphic sender (User or Customer)
    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

    // optional polymorphic receiver
    public function receiver(): MorphTo
    {
        return $this->morphTo();
    }

    public function attachments()
    {
        return $this->hasMany(MessageAttachment::class, 'message_id', 'id');
    }
}
