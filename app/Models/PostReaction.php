<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostReaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'user_id',
        'type',
    ];

    /**
     * The post this reaction belongs to
     */
    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * The user who reacted
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
