<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ConversationReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date', 'date_format:Y-m-d', 'before_or_equal:end_date'],
            'end_date'   => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required'         => 'Start date is required.',
            'start_date.date'             => 'Start date must be a valid date.',
            'start_date.date_format'      => 'Start date must be in Y-m-d format (e.g., 2025-01-30).',
            'start_date.before_or_equal'  => 'Start date cannot be greater than end date.',
            'end_date.required'           => 'End date is required.',
            'end_date.date'               => 'End date must be a valid date.',
            'end_date.date_format'        => 'End date must be in Y-m-d format (e.g., 2025-01-30).',
            'end_date.after_or_equal'     => 'End date cannot be less than start date.',
        ];
    }

    protected function failedValidation(Validator $validator)
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
