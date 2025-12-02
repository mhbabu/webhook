<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'agent_id',
        'platform',
        'trace_id',
        'started_at',
        'end_at',
        'ended_by',
        'wrap_up_id',
        'in_queue_at',
        'first_message_at',
        'last_message_at',
        'agent_assigned_at',
        'last_message_id'
    ];

    protected $casts = [
        'started_at'        => 'datetime',
        'end_at'            => 'datetime',
        'in_queue_at'       => 'datetime',
        'first_message_at'  => 'datetime',
        'last_message_at'   => 'datetime',
        'agent_assigned_at' => 'datetime'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function lastMessage()
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function wrapUp()
    {
        return $this->belongsTo(WrapUpConversation::class, 'wrap_up_id', 'id');
    }
}
