<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'platform_id'
    ];

    /**
     * Get the platform this customer belongs to.
     */
    public function platform()
    {
        return $this->belongsTo(Platform::class);
    }

    /**
     * Get all conversations of the customer.
     */
    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function messagesSent()
    {
        return $this->morphMany(Message::class, 'sender');
    }

    public function messagesReceived()
    {
        return $this->morphMany(Message::class, 'receiver');
    }
}
