<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\Role;  // Make sure to import the Role model
use App\Models\RoleHierarchy;

class StoreRoleRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get the authenticated user's role
        $userRole         = auth()->user()->role;
        $userChildRoleIds = RoleHierarchy::where('parent_role_id', $userRole->id)->pluck('child_role_id'); // Get allowed child roles for the user

        return [
            'name'       => ['required', 'string', 'max:255', 'unique:roles,name'],
            'role_ids'   => ['nullable', 'array'],
            'role_ids.*' => [
                'exists:roles,id',
                function ($attribute, $value, $fail) use ($userChildRoleIds, $userRole) {
                    $roleBeingCreated = Role::find($value);

                    if ($roleBeingCreated) {
                        // Check if the role being created is in the user's allowed child roles
                        if (!$userChildRoleIds->contains($value)) {
                            $fail("You are not authorized to create the '{$roleBeingCreated->name}' role because your role is '{$userRole->name}'.");
                        }
                    }
                },
            ],
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
