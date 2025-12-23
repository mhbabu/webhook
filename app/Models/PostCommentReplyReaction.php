<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostCommentReplyReaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'post_comment_reply_reactions';

    // Fillable columns
    protected $fillable = [
        'post_id',          // Optional if you track post
        'customer_id',      // User who reacted
        'platform_reply_id',// External platform reply ID (Facebook)
        'type',             // Reaction type
    ];

    // Casts
    protected $casts = [
        'type' => 'string',
        'deleted_at' => 'datetime',
    ];

    // Relationships

    /**
     * Customer who reacted
     */
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id'); // Or FacebookUser
    }

    /**
     * Reply reaction belongs to a comment reply
     */
    public function commentReply()
    {
        return $this->belongsTo(PostCommentReply::class, 'platform_reply_id', 'platform_reply_id');
    }
}
