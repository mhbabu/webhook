<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Platform extends Model
{
    protected $table = "platforms";
    protected $fillable = ['name', 'status'];

}
