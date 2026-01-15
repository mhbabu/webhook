<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerMode extends Model
{
    protected $table = 'customer_modes';

    protected $fillable = ['name', 'is_active'];
}
