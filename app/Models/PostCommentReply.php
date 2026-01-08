<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostCommentReply extends Model
{
    protected $fillable = [
        'post_comment_id','platform_reply_id','customer_id','content',
        'attachment','mentions',
        'replied_at','edited_at','deleted_at','updated_by','deleted_by', 'attachment'
    ];

    protected $casts = [
        'attachment' =>'array',
        'mentions'   =>'array',
        'replied_at' =>'datetime',
        'edited_at'  =>'datetime',
        'deleted_at' =>'datetime',
    ];

    public function comment(){ 
        return $this->belongsTo(PostComment::class,'post_comment_id'); 
    }

    public function customer(){ 
        return $this->belongsTo(User::class,'customer_id'); 
    }
}

