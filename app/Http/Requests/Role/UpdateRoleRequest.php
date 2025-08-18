<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use App\Models\Role;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;  // You can add custom logic here to check if the user is authorized
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get the authenticated user's role
        $userRole         = auth()->user()->role;
        $userChildRoleIds = $userRole->childRoles()->pluck('child_role_id'); // Get allowed child roles for the user

        return [
            'name'     => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($this->route('role')->id ?? null)],
            'status'   => 'required|integer|in:0,1',
            'role_ids' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) use ($userChildRoleIds, $userRole) {
                    // Check if the role being updated is allowed to be modified based on user role permissions
                    $roleBeingUpdated = Role::find($this->route('role')->id);

                    if ($roleBeingUpdated) {
                        // Check if the user is authorized to update this role
                        if (!$userChildRoleIds->contains($roleBeingUpdated->id)) {
                            $fail("You are not authorized to update the '{$roleBeingUpdated->name}' role because your role is '{$userRole->name}'.");
                        }
                    }
                },
            ],
        ];
    }

    /**
     * Get the custom error messages for the validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.integer'  => 'The status field must be an integer.',
            'status.in'       => 'The status field must be 0 or 1.',
            'status.required' => 'The status field is required.',
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
