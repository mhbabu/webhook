<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationCategory extends Model
{
    protected $fillable = ['name', 'is_active'];

    public function subCategories()
    {
        return $this->hasMany(
            ConversationSubCategory::class,
            'conversation_category_id'
        );
    }
}
