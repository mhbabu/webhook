<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Disposition extends Model
{
    protected $table = 'dispositions';

    protected $fillable = [
        'name',
        'is_active',
    ];

    public function subDispositions()
    {
        return $this->hasMany(SubDisposition::class, 'disposition_id');
    }
}
