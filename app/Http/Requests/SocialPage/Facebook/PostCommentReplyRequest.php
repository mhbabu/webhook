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
            'content' => ['required', 'string', 'max:2000'], // only content is required
        ];
    }

    /**
     * Custom error messages
     */
    public function messages(): array
    {
        return [
            'content.required' => 'The content field is required.',
            'content.string'   => 'The content must be a valid string.',
            'content.max'      => 'The content must not exceed 2000 characters.',
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
