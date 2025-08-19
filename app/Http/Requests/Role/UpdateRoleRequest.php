<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use App\Models\Role;
use App\Models\RoleHierarchy;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // You can add custom logic here if needed
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $userRole         = auth()->user()->role;
        $roleId           = $this->route('role'); // Use the route parameter directly
        $userChildRoleIds = RoleHierarchy::where('parent_role_id', $userRole->id)->pluck('child_role_id');

        return [
            'name'     => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($roleId)],
            'role_ids' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) use ($userChildRoleIds, $userRole, $roleId) {
                    $roleBeingUpdated = Role::find($roleId);

                    if ($roleBeingUpdated && !$userChildRoleIds->contains($roleBeingUpdated->id)) {
                        $fail("You are not authorized to update the '{$roleBeingUpdated->name}' role because your role is '{$userRole->name}'.");
                    }
                },
            ],
        ];
    }

    /**
     * Handle a failed validation attempt.
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
