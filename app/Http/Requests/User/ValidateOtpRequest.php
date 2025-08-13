<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\OtpService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ValidateOtpRequest extends FormRequest
{
    protected $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    public function rules()
    {
        return [
            'otp' => [
                'required',
                'string',
                'size:6', // Assuming OTP is 6 digits
                function ($attribute, $value, $fail) {
                    $validOtp = $this->otpService->getValidOtp($value);
                    
                    if (!$validOtp) {
                        $expiredOtp = $this->otpService->getExpiredOtp($value);
                        
                        if ($expiredOtp) {
                            $fail('The OTP has expired.');
                        } else {
                            $fail('The OTP is invalid.');
                        }
                    }
                },
            ],
        ];
    }

    public function messages()
    {
        return [
            'otp.required' => 'The OTP field is required.',
            'otp.size'     => 'The OTP must be 6 digits.',
        ];
    }

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

