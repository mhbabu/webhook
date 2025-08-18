<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCategory extends Model
{
    protected $table = "user_categories";
    protected $fillable = ['name', 'status'];
}
