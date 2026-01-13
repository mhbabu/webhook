<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubDisposition extends Model
{
    protected $table = 'sub_dispositions';

    protected $fillable = [
        'disposition_id',
        'name',
        'is_active',
    ];

    public function disposition()
    {
        return $this->belongsTo(Disposition::class, 'disposition_id');
    }
}
