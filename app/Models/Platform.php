<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Platform extends Model
{
    protected $table = 'platforms';

    protected $fillable = ['name', 'status'];

    public function accounts()
    {
        return $this->hasMany(PlatformAccount::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
