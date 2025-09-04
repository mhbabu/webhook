<?php

namespace App\Http\Requests\User\Status;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\UserStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StatusUpdateRequest extends FormRequest
{
    /**
     * Authorize the request.
     */
    public function authorize(): bool
    {
        return true;
    }



    public function rules(): array
    {
        $allowedStatuses = array_map(fn($status) => $status->value, UserStatus::cases());

        return [
            'status' => ['required', Rule::in($allowedStatuses)],
            'reason' => ['nullable', 'string', 'max:255', 'required_if:status,' . UserStatus::BREAK_REQUEST->value],
        ];
    }

    public function messages(): array
    {
        $allowedStatuses = implode(', ', array_map(fn($status) => $status->value, UserStatus::cases()));

        return [
            'status.required'    => 'The status field is required.',
            'status.in'          => 'Invalid status. Allowed values are: ' . $allowedStatuses,
            'reason.required_if' => 'Reason is required when status is BREAK REQUEST.',
            'reason.max'         => 'The reason must not exceed 255 characters.',
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
            'errors'  => $errors,
        ], 422);

        throw new HttpResponseException($response);
    }
}
