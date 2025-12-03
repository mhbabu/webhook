<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConversationRating extends Model
{
    use HasFactory;

    protected $table = 'conversation_ratings';

    protected $fillable = [
        'conversation_id',
        'agent_id',
        'platform',
        'option_label',
        'rating_value',
        'interactive_type',
        'comments',
    ];

    /**
     * Each rating belongs to one conversation
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Access the agent who handled this conversation
     */
    public function agent()
    {
        return $this->hasOneThrough(
            User::class,             // Final model
            Conversation::class,     // Through model
            'id',                    // Foreign key on Conversation table (local key)
            'id',                    // Foreign key on User table (agent_id)
            'conversation_id',       // Local key on ConversationRating
            'agent_id'               // Local key on Conversation
        );
    }

    /**
     * Access the customer who owns this conversation
     */
    public function customer()
    {
        return $this->hasOneThrough(
            Customer::class,         // Final model
            Conversation::class,     // Through model
            'id',                    // Foreign key on Conversation table (local key)
            'id',                    // Foreign key on Customer table
            'conversation_id',       // Local key on ConversationRating
            'customer_id'            // Local key on Conversation
        );
    }
}
