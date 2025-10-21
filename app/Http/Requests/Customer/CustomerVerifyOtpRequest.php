<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\Platform;
use Illuminate\Support\Facades\DB;

class CustomerVerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $platformId = Platform::where('name', 'website')->value('id');

        return [
            'otp'   => 'required|digits:6',
            'email' => ['required', 'email',
                function ($attribute, $value, $fail) use ($platformId) {
                    $exists = DB::table('customers')
                        ->where('email', $value)
                        ->where('platform_id', $platformId)
                        ->exists();

                    if (! $exists) {
                        $fail('The provided email does not exist for the website platform.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'otp.required'   => 'OTP is required.',
            'otp.digits'     => 'OTP must be exactly 6 digits.',
            'email.required' => 'Email address is required.',
            'email.email'    => 'Please provide a valid email address.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors()
            ], 422)
        );
    }
}
