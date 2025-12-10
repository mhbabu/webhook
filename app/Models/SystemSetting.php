<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['setting_key', 'setting_value'];

    protected $casts = [
        'setting_value' => 'array', // automatically cast JSON to array
    ];
}
