<?php

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class EndConversationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'conversation_id'      => ['required', 'exists:conversations,id'],
            'wrap_up_id'           => ['required', 'exists:wrap_up_conversations,id'],
            'sub_wrap_up_id'       => ['required', 'exists:subwrap_up_conversations,id'], 
            'conversation_type_id' => ['required', 'exists:conversation_types,id'],
            'customer_mode_id'     => ['required', 'exists:customer_modes,id'],
            'remarks'              => ['nullable', 'string'],
        ];
    }

    /**
     * Custom validation error messages.
     */
    public function messages(): array
    {
        return [
            'conversation_id.required'      => 'Conversation is required.',
            'conversation_id.exists'        => 'The selected conversation does not exist.',

            'wrap_up_id.required'           => 'Wrap-up conversation is required.',
            'wrap_up_id.exists'             => 'The selected wrap-up conversation is invalid.',

            'sub_wrap_up_id.required'       => 'Sub wrap-up conversation is required.',
            'sub_wrap_up_id.exists'         => 'The selected sub wrap-up conversation is invalid.',

            'conversation_type_id.required' => 'Conversation type is required.',
            'conversation_type_id.exists'   => 'The selected conversation type is invalid.',

            'customer_mode_id.required'     => 'Customer mode is required.',
            'customer_mode_id.exists'       => 'The selected customer mode is invalid.',

            'remarks.string'                => 'Remarks must be a valid text.',
        ];
    }

    /**
     * Handle failed validation response.
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
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
