<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserQuickReply extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'content',
        'status',
    ];
}
