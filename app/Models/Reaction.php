<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reaction extends Model
{
    protected $fillable = [
        'post_id', 'comment_id', 'platform_reaction_id', 'user_platform_id',
        'customer_id', 'reaction_type', 'reacted_at', 'raw',
    ];

    protected $casts = ['raw' => 'array', 'reacted_at' => 'datetime'];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function comment()
    {
        return $this->belongsTo(Comment::class);
    }

    public function customer()
    {
        return $this->belongsTo(\App\Models\Customer::class, 'customer_id');
    }
}
