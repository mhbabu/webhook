<?php

namespace App\Http\Requests\ConversationSummary;

use Illuminate\Foundation\Http\FormRequest;

class ConversationTypeRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name' => 'required|string|max:100|unique:conversation_types,name,'.$id,
            'is_active' => 'sometimes|boolean',
        ];
    }
}
