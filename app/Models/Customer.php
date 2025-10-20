<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Customer extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'platform_id',
        'platform_user_id',
        'is_verified',
        'is_requested',
        'token',
        'token_expires_at'
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile_photo');
    }


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
        return $this->hasMany(Conversation::class, 'customer_id');
    }

    public function messagesSent()
    {
        return $this->morphMany(Message::class, 'sender');
    }

    public function messagesReceived()
    {
        return $this->morphMany(Message::class, 'receiver');
    }

    // One customer has one conversation
    public function conversation()
    {
        return $this->hasOne(Conversation::class);
    }

    // Optional: if you want all messages of this customer
    public function messages()
    {
        return $this->hasManyThrough(Message::class, Conversation::class);
    }
}
