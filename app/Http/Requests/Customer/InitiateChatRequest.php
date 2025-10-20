<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class InitiateChatRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define validation rules.
     */
    public function rules(): array
    {
        return [
            'name'   => 'required|string|max:255',
            'email'  => 'required|email',
            'phone'  => ['required', 'regex:/^(?:\+88|88)?01[3-9]\d{8}$/'],
        ];
    }

    /**
     * Define custom error messages.
     */
    public function messages(): array
    {
        return [
            'name.required'        => 'Name is required.',
            'email.required'       => 'Email is required.',
            'email.email'          => 'Invalid email format.',
            'phone.required'       => 'Phone number is required.',
            'phone.regex'          => 'Phone number must be a valid Bangladeshi number.'
        ];
    }

    /**
     * Custom failed validation response.
     */
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
