<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAccount extends Model
{
    protected $fillable = ['platform_id', 'platform_account_id', 'name', 'username', 'credentials'];

    protected $casts = ['credentials' => 'array'];

    public function platform()
    {
        return $this->belongsTo(Platform::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
