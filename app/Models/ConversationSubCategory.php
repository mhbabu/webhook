<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationSubCategory extends Model
{
    protected $fillable = [
        'conversation_category_id',
        'name',
        'is_active',
    ];

    public function category()
    {
        return $this->belongsTo(
            ConversationCategory::class,
            'conversation_category_id'
        );
    }
}
