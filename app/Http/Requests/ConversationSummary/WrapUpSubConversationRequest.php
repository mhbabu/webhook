<?php

namespace App\Http\Requests\ConversationSummary;

use Illuminate\Foundation\Http\FormRequest;

class WrapUpSubConversationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'wrap_up_conversation_id' => 'required|exists:wrap_up_conversations,id',
            'name' => 'required|string|max:100',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
