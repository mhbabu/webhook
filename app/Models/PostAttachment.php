<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'type',
        'url',
        'thumbnail_url',
        'description',
        'tags',
        'position',
        'metadata',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
    ];

    // Relationship with Post
    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
