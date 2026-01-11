<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostReaction extends Model
{
    protected $fillable = ['post_id','platform_user_id','type'];
    public function post(){ return $this->belongsTo(Post::class); }
}
