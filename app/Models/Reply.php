<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reply extends Model
{
    protected $fillable = ['comment_id', 'user_id', 'content'];

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function comment()
    {
        return $this->belongsTo(Comment::class);
    }

    public function reactions()
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }
}
