<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    use HasFactory;

    protected $table = 'message_templates';

    protected $fillable = [
        'type',
        'content',
        'is_active',
        'remarks',
        'options',
        'logo'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'options'   => 'array', // <-- cast JSON to array
    ];
}
