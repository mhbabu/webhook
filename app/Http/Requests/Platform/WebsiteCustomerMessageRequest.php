<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class WebsiteCustomerMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ✅ content is optional but required if attachments are missing
            'content'       => ['nullable', 'string', 'required_without:attachments'],

            // ✅ attachments are optional but required if content is missing
            'attachments'   => ['nullable', 'array', 'required_without:content'],
            'attachments.*' => ['nullable', 'file', 'mimes:jpeg,jpg,png,gif,webp,mp3,wav,mp4,mov,avi,flv,pdf,doc,docx', 'max:5120'], // 5MB max per file
        ];
    }

    public function messages(): array
    {
        return [
            'content.required_without'     => 'You must provide either message content or at least one attachment.',
            'attachments.required_without' => 'You must provide either message content or at least one attachment.',
            'attachments.*.mimes'          => 'Only image, audio, video, or document files are allowed.',
            'attachments.*.max'            => 'Each file must not exceed 5MB.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'  => false,
            'message' => $validator->errors()->first(),
            'errors'  => $validator->errors(),
        ], 422));
    }
}
