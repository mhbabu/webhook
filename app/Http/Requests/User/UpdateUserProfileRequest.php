<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateUserProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // Only authenticated users can update
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userType = auth()->user()->type ?? null;

        $rules = [
            'name'            => ['sometimes', 'string', 'max:255'],
            'mobile'          => ['sometimes', 'string', 'regex:/^01[3-9][0-9]{8}$/', Rule::unique('users')->ignore($this->user()->id ?? null)],
            'profile_picture' => ['sometimes', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];

        // Only admins or supervisors can update these fields
        if (in_array($userType, ['System Admin', 'Admin', 'Supervisor'])) {
            $rules['email']         = ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->user()->id ?? null)];
            $rules['employee_id']   = ['required', 'string', 'max:255', Rule::unique('users')->ignore($this->user()->id ?? null)];
            $rules['max_limit']     = ['required', 'integer', 'min:1'];
            $rules['role_id']       = ['required', 'string', 'max:255'];
            $rules['category_id']   = ['required', 'string', 'max:255'];
        }

        return $rules;
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
     * Handle a failed validation attempt and return JSON.
     *
     * @param  Validator  $validator
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();

        throw new HttpResponseException(
            response()->json([
                'status'  => false,
                'message' => $errors->first(),
                'errors'  => $errors,
            ], 422)
        );
    }
}
