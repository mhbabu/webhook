<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostCommentReply extends Model
{
    use HasFactory;

    protected $table = 'post_comment_replies';

    // Fillable fields
    protected $fillable = [
        'comment_id',        // Parent comment
        'platform_reply_id', // External platform reply ID (Facebook)
        'customer_id',       // User who replied
        'content',           // Reply text
        'attachment_type',   // image, gif, video, document
        'attachment_path',   // Path to attachment
        'is_top_comment',    // Optional pinned flag
        'replied_at',        // Timestamp when replied
        'edited_at',         // Edited timestamp
        'deleted_at',        // Deleted timestamp
        'updated_by',        // User who updated
        'deleted_by',        // User who deleted
    ];

    // Casts for date and boolean columns
    protected $casts = [
        'is_top_comment' => 'boolean',
        'replied_at'     => 'datetime',
        'edited_at'      => 'datetime',
        'deleted_at'     => 'datetime',
    ];

    // Relationships

    /**
     * Parent comment
     */
    public function comment()
    {
        return $this->belongsTo(PostComment::class, 'comment_id');
    }

    /**
     * Customer / user who replied
     */
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id'); // Or FacebookUser
    }

    /**
     * User who updated the reply
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * User who deleted the reply
     */
    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // Accessor for attachment URL (if using public disk)
    public function getAttachmentUrlAttribute()
    {
        if ($this->attachment_path) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($this->attachment_path);
        }
        return null;
    }

    /**
     * Reactions for this reply
     */
    public function reactions()
    {
        return $this->hasMany(PostCommentReplyReaction::class, 'platform_reply_id', 'platform_reply_id');
    }
}
