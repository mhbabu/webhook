<?php

namespace App\Services;

use App\Models\OtpVerification;
use Carbon\Carbon;

class OtpService
{
    /**
     * Generate a new OTP and save it in the database.
     */
    public function generateOtp(int $userId): string
    {
        $otp = mt_rand(100000, 999999);

        OtpVerification::create([
            'user_id'   => $userId,
            'otp'       => $otp,
            'expire_at' => Carbon::now()->addMinutes(5),
        ]);

        return $otp;
    }

    /**
     * Get a valid OTP.
     */
    public function getValidOtp(string $otp): ?OtpVerification
    {
        return $this->getOtp($otp, '>');
    }

    /**
     * Get an expired OTP.
     */
    public function getExpiredOtp(string $otp): ?OtpVerification
    {
        return $this->getOtp($otp, '<=');
    }

    /**
     * Helper method to get OTP based on condition.
     */
    private function getOtp(string $otp, string $condition): ?OtpVerification
    {
        return OtpVerification::where('otp', $otp)
            ->where('expire_at', $condition, Carbon::now())
            ->first();
    }

    /**
     * Delete OTP from the database.
     */
    public function deleteOtp(int $userId): void
    {
        OtpVerification::where('user_id', $userId)->delete();
    }
}
