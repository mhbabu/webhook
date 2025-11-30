<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConversationTemplateMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'template_id',
        'customer_id',
        'content',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function template()
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
