<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $fillable = [
        'post_id', 'platform_comment_id', 'platform_parent_id',
        'author_platform_id', 'customer_id', 'author_name',
        'message', 'commented_at', 'raw',
    ];

    protected $casts = ['raw' => 'array', 'commented_at' => 'datetime'];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function customer()
    {
        return $this->belongsTo(\App\Models\Customer::class, 'customer_id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'platform_parent_id', 'platform_comment_id');
    }

    public function reactions()
    {
        return $this->hasMany(Reaction::class);
    }
}
