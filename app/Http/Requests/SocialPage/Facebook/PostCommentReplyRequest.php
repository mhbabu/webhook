<?php

namespace App\Http\Requests\SocialPage\Facebook;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class PostCommentReplyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // allow all requests
    }

    /**
     * Validation rules
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'], // only message is required
        ];
    }

    /**
     * Custom error messages
     */
    public function messages(): array
    {
        return [
            'message.required' => 'The message field is required.',
            'message.string'   => 'The message must be a valid string.',
            'message.max'      => 'The message must not exceed 2000 characters.',
        ];
    }

    /**
     * Handle failed validation and return JSON
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors();
        $response = response()->json(['status'  => false, 'message' => $errors->first(), 'errors'  => $errors,], 422);
        throw new HttpResponseException($response);
    }
}
