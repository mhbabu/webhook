<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'facebook_id',
        'content',
        'source_id',
        'privacy',
        'posted_by',
        'edited_by',
        'deleted_by',
        'posted_at',
        'updated_at',
        'deleted_at',
        'scheduled_at',
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
    ];

    protected $casts = [
        'reactions'    => 'array',
        'tags'         => 'array',
        'hashtags'     => 'array',
        'posted_at'    => 'datetime',
        'updated_at'   => 'datetime',
        'deleted_at'   => 'datetime',
        'scheduled_at' => 'datetime',
        'is_pinned'    => 'boolean',
        'is_sponsored' => 'boolean'
    ];

    // Relationships
    public function attachments()
    {
        return $this->hasMany(PostAttachment::class);
    }

    public function postedBy()
    {
        return $this->belongsTo(Customer::class, 'posted_by');
    }

    public function editedBy()
    {
        return $this->belongsTo(Customer::class, 'edited_by');
    }

    public function deletedBy()
    {
        return $this->belongsTo(Customer::class, 'deleted_by');
    }
}
