<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostCommentReaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'comment_reactions'; // your table name

    protected $fillable = [
        'post_comment_id',   // Top-level comment
        'comment_reply_id',  // Optional if reaction is for a reply
        'customer_id',       // User who reacted
        'reaction_type',     // like, love, haha, wow, sad, angry
    ];

    protected $casts = [
        'reaction_type' => 'string',
        'deleted_at' => 'datetime',
    ];

    // Relationships

    /**
     * The comment this reaction belongs to
     */
    public function postComment()
    {
        return $this->belongsTo(PostComment::class, 'post_comment_id');
    }

    /**
     * The reply this reaction belongs to
     */
    public function commentReply()
    {
        return $this->belongsTo(PostCommentReply::class, 'comment_reply_id');
    }

    /**
     * The customer/user who reacted
     */
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id'); // or FacebookUser
    }
}
