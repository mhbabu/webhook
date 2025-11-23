<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $fillable = ['platform_id', 'platform_account_id', 'sync_type', 'status', 'message'];
}
