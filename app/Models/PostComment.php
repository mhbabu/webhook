<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostComment extends Model
{
    protected $fillable = [
        'post_id',
        'platform_comment_id',
        'customer_id',
        'content',
        'attachment',
        'is_top_comment',
        'mentions',
        'commented_at',
        'edited_at',
        'deleted_at',
        'updated_by',
        'deleted_by'
    ];

    protected $casts = [
        'mentions'     => 'array',
        'attachment'  => 'array',
        'commented_at' => 'datetime',
        'edited_at'    => 'datetime',
        'deleted_at'   => 'datetime',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function replies()
    {
        return $this->hasMany(PostCommentReply::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
