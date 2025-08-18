<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateNewAgenRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'employee_id'     => ['required', 'string', 'unique:users'],
            'mobile'          => ['required', 'string', 'regex:/^01[3-9][0-9]{8}$/', 'unique:users'],
            'max_limit'       => ['required', 'integer'],
            'platform_ids'    => ['required', 'array'],
            'platform_ids.*'  => ['integer', 'exists:platforms,id'],
            'profile_picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }

    /**
     * Custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mobile.regex'           => 'The mobile number must be a valid Bangladeshi number (11 digits starting with 01).',
            'profile_picture.max'    => 'The profile picture must not be greater than 2 MB.',
            'profile_picture.image'  => 'The profile picture must be an image.',
            'profile_picture.mimes'  => 'The profile picture must be a file of type: jpg, jpeg, png.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors();
        $response = response()->json([
            'status'  => false,
            'message' => $errors->first(),
            'errors'  => $errors
        ], 422);

        throw new HttpResponseException($response);
    }
}
