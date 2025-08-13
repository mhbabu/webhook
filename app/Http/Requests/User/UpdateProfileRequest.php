<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateProfileRequest extends FormRequest
{
    public function rules()
    {
        return [
            'birth_date'   => 'required|date|date_format:Y-m-d|before:today',
            'profession'   => 'required|string|max:255',
            'gender'       => 'required|in:male,female,common',
            'languages'    => 'nullable|array',
            'location'     => 'nullable|string|max:255',
            'address'      => 'nullable|string',
            'image'        => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
        ];
    }

    public function messages()
    {
        return [
            'image.max' => 'The Profile picture must not be greater than 2 MB.'
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
