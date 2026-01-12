<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerMode extends Model
{
    protected $fillable = ['interaction_type_id', 'name', 'is_active'];

    public function interactionType()
    {
        return $this->belongsTo(InteractionType::class);
    }
}
