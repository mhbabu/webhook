<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateUserProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Get the user ID from route model binding
        $updatingUserId = $this->route('user')->id ?? null;

        return [
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['required', 'email', Rule::unique('users', 'email')->ignore($updatingUserId)],
            'mobile'          => ['required', 'regex:/^01[3-9][0-9]{8}$/', Rule::unique('users', 'mobile')->ignore($updatingUserId)],
            'employee_id'     => ['required', 'string', 'max:255', Rule::unique('users', 'employee_id')->ignore($updatingUserId)],
            'max_limit'       => ['required', 'integer', 'min:1'],
            'role_id'         => ['nullable', 'integer', 'exists:roles,id'],
            'platforms'       => ['required', 'array'],
            'platforms.*'     => ['integer', 'distinct', 'exists:platforms,id'],
            'profile_picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.regex'           => 'The mobile number must be a valid Bangladeshi number (11 digits starting with 01).',
            'mobile.unique'          => 'This mobile number is already registered.',
            'email.unique'           => 'This email address is already registered.',
            'employee_id.unique'     => 'This employee ID is already in use.',
            'profile_picture.max'    => 'The profile picture must not be greater than 2 MB.',
            'profile_picture.image'  => 'The profile picture must be an image.',
            'profile_picture.mimes'  => 'The profile picture must be a file of type: jpg, jpeg, png.',
        ];
    }

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
