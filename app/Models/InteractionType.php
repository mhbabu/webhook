<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InteractionType extends Model
{
    protected $fillable = ['name', 'is_active'];

    public function customerModes()
    {
        return $this->hasMany(CustomerMode::class);
    }
}
