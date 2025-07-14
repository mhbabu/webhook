<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentPlatformRole extends Model
{
    protected $fillable = ['agent_id', 'platform_id', 'status', 'current_load', 'max_limit'];

    public function agent() { 
        return $this->belongsTo(User::class, 'agent_id'); 
    }
    public function platform() { 
        return $this->belongsTo(Platform::class); 
    }
}
