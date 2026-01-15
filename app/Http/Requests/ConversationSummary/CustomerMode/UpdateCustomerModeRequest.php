<?php

namespace App\Http\Requests\ConversationSummary\CustomerMode;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateCustomerModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // {type} is actually the ID (string)
        $id = (int) $this->route('type');

        return [
            'name'      => ['required', 'string', 'max:255', Rule::unique('conversation_types', 'name')->ignore($id)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'The name field is required.',
            'name.unique'       => 'This conversation type already exists.',
            'is_active.boolean' => 'The active status must be true or false (0 or 1).',
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
