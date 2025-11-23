<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostComment extends Model
{
    use HasFactory;

    // Fillable fields
    protected $fillable = [
        'post_id',         // Reference to the post
        'customer_id',     // Facebook user ID or internal user
        'content',         // Comment text
        'attachment_type', // image, gif, video, document
        'attachment_path', // path to the attachment
        'is_top_comment',  // top/pinned comment
        'commented_at',    // posted timestamp
        'edited_at',       // edited timestamp
        'deleted_at',      // deleted timestamp
        'updated_by',      // user who updated
        'deleted_by',      // user who deleted
    ];

    // Cast columns to proper types
    protected $casts = [
        'is_top_comment' => 'boolean',
        'mentions'       => 'array',
        'commented_at'   => 'datetime',
        'edited_at'      => 'datetime',
        'deleted_at'     => 'datetime',
    ];

    // Relationships

    /**
     * The post this comment belongs to
     */
    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * The user who commented
     */
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id'); // replace User with FacebookUser if needed
    }

    /**
     * The user who updated the comment
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The user who deleted the comment
     */
    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // Helper to get attachment URL if using 'public' disk
    public function getAttachmentUrlAttribute()
    {
        if ($this->attachment_path) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($this->attachment_path);
        }
        return null;
    }
}
