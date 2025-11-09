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
        // Get the target user ID from route (works for {userId} or {user})
        $user = $this->route('userId') ?? $this->route('user');
        $userId = $user?->id ?? $user; // if it's a model, take id; otherwise use as-is

        // Authenticated user's role
        $authRoleName = auth()->user()?->role?->name ?? null;

        // Base rules for all users
        $rules = [
            'name'            => ['required', 'string', 'max:255'],
            'mobile'          => [
                'required',
                'regex:/^01[3-9][0-9]{8}$/',
                Rule::unique('users', 'mobile')->ignore($userId, 'id'),
            ],
            'profile_picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];

        // Super Admin rules
        if ($authRoleName === 'Super Admin') {
            $rules = array_merge($rules, [
                'email'       => [
                    'required',
                    'email',
                    Rule::unique('users', 'email')->ignore($userId, 'id'),
                ],
                'employee_id' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('users', 'employee_id')->ignore($userId, 'id'),
                ],
                'max_limit'   => ['required', 'integer', 'min:1'],
                'role_id'     => ['nullable', 'integer', 'exists:roles,id'],
                'platforms'   => ['required', 'array'],
                'platforms.*' => ['integer', 'distinct', 'exists:platforms,id'],
            ]);
        }

        return $rules;
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
