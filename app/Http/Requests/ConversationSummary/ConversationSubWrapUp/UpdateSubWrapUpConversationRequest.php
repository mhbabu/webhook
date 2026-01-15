<?php

namespace App\Http\Requests\ConversationSummary\ConversationSubWrapUp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateSubWrapUpConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Use route parameter $id from controller
        $id = $this->route('wrap_up_conversation');
        info($id);

        return [
            'name'                    => ['required', 'string', 'max:255', Rule::unique('wrap_up_sub_conversations')->where('wrap_up_conversation_id', $this->wrap_up_conversation_id)->ignore($id)],
            'wrap_up_conversation_id' => [ 'required', 'integer', 'exists:wrap_up_conversations,id'],
            'is_active'               => [ 'required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                     => 'The sub wrap-up name is required.',
            'name.unique'                       => 'This sub wrap-up already exists for this wrap-up.',
            'wrap_up_conversation_id.required'  => 'The wrap-up conversation is required.',
            'wrap_up_conversation_id.exists'    => 'The selected wrap-up conversation is invalid.',
            'is_active.required'                => 'The active status is required.',
            'is_active.boolean'                 => 'The active status must be true or false (0 or 1).',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
