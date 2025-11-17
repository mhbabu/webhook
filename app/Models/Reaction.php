<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reaction extends Model
{
    protected $fillable = [
        'user_id',
        'reaction_type',
        'reactable_id',
        'reactable_type'
    ];

    public function reactable()
    {
        return $this->morphTo();
    }
}
