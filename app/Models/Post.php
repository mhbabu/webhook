<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform_post_id',
        'source',
        'content',
        'privacy',
        'posted_by',
        'edited_by',
        'deleted_by',
        'posted_at',
        'scheduled_at',
        'deleted_at',
        'comments_count',
        'shares_count',
        'reactions',
        'tags',
        'hashtags',
        'permalink_url',
        'location',
        'feeling',
        'activity',
        'post_type',
        'language',
        'is_pinned',
        'is_sponsored',
        'attachment'
    ];

    protected $casts = [
        'privacy'      => 'array',
        'location'     => 'array',
        'tags'         => 'array',
        'reactions'    => 'array',
        'hashtags'     => 'array',
        'attachment'   => 'array',
        'posted_at'    => 'datetime',
        'scheduled_at' => 'datetime',
        'deleted_at'   => 'datetime',
    ];

    public function comments()
    {
        return $this->hasMany(PostComment::class);
    }

    public function reactionsList()
    {
        return $this->hasMany(PostReaction::class);
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function editedBy()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
    
    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
