<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = [
        'platform_id', 'platform_account_id', 'platform_post_id',
        'type', 'caption', 'posted_at', 'raw',
    ];

    protected $casts = ['raw' => 'array', 'posted_at' => 'datetime'];

    public function platform()
    {
        return $this->belongsTo(Platform::class);
    }

    public function account()
    {
        return $this->belongsTo(PlatformAccount::class, 'platform_account_id');
    }

    public function media()
    {
        return $this->hasMany(PostMedia::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions()
    {
        return $this->hasMany(Reaction::class);
    }
}
