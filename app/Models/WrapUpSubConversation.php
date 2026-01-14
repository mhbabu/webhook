<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WrapUpSubConversation extends Model
{
    protected $fillable = [
        'wrap_up_conversation_id',
        'name',
        'is_active',
    ];

    public function wrapUpConversation()
    {
        return $this->belongsTo(WrapUpConversation::class);
    }
}
