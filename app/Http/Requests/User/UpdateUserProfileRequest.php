<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateUserProfileRequest extends FormRequest
{
    /**
     * Authorization (FAIL FAST)
     */
    public function authorize(): bool
    {
        $roleName = strtolower(trim(auth()->user()?->role?->name ?? ''));
        return in_array($roleName, ['super admin', 'supervisor'], true);
    }

    /**
     * Validation rules
     */
    public function rules(): array
    {
        // Get user id from route
        $user = $this->route('userId') ?? $this->route('user');
        $userId = $user?->id ?? $user;

        return [
            'name' => ['required', 'string', 'max:255'],

            'mobile' => [
                'required',
                'regex:/^01[3-9][0-9]{8}$/',
                Rule::unique('users', 'mobile')->ignore($userId),
            ],

            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($userId),
            ],

            'employee_id' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'employee_id')->ignore($userId),
            ],

            'max_limit' => ['required', 'integer', 'min:1'],

            'role_id' => ['nullable', 'integer', 'exists:roles,id'],

            'platform_ids' => ['required', 'array'],
            'platform_ids.*' => ['integer', 'distinct', 'exists:platforms,id'],

            'profile_picture' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png',
                'max:2048',
            ],
        ];
    }

    /**
     * Custom messages
     */
    public function messages(): array
    {
        return [
            'mobile.regex'        => 'The mobile number must be a valid Bangladeshi number.',
            'mobile.unique'       => 'This mobile number is already registered.',
            'email.unique'        => 'This email address is already registered.',
            'employee_id.unique'  => 'This employee ID is already in use.',
            'platform_ids.required' => 'At least one platform must be selected.',
        ];
    }

    /**
     * Validation failure response (422)
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

    /**
     * Authorization failure response (403)
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'status'  => false,
                'message' => 'You are not authorized to update user profiles.',
            ], 403)
        );
    }
}
