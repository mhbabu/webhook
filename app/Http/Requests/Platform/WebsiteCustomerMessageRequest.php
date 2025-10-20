<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class WebsiteCustomerMessageRequest extends FormRequest
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
            'token'         => ['required', 'string'],
            'content'       => ['required', 'string'],
            'attachments'   => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'mimes:jpeg,jpg,png,gif,webp,mp3,wav,mp4,mov,avi,flv,pdf,doc,docx', 'max:5120'], // Max 5MB per file (5120 KB) 
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'  => false,
            'message' => $validator->errors()->first(),
            'errors'  => $validator->errors(),
        ], 422));
    }
}
