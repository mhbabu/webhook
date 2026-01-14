<?php

namespace App\Http\Requests\ConversationSummary;

use Illuminate\Foundation\Http\FormRequest;

class CustomerModeRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name' => 'required|string|max:100|unique:customer_modes,name,'.$id,
            'is_active' => 'sometimes|boolean',
        ];
    }
}
