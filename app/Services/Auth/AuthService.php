<?php

namespace App\Services\User;

use App\Http\Resources\User\UserResource;
use App\Models\User;
use App\Notifications\AccountVerificationNotification;
use App\Services\OtpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Exception;

class AuthService
{
    protected $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Register a new user and send OTP.
     */
    public function registerUser(string $name, string $email, string $password): array
    {
        try {
            DB::beginTransaction();

            $user = User::create([
                'name'     => $name,
                'email'    => strtolower($email),
                'password' => Hash::make($password),
            ]);

            $otp = $this->otpService->generateOtp($user->id);
            // $user->notify(new AccountVerificationNotification($user, $otp));

            DB::commit();

            // return ['data' => ['message' => 'OTP sent to your mail'], 'status' => 200];
            return ['data' => ['message' => $otp], 'status' => 200];
        } catch (Exception $e) {
            DB::rollBack();
            return ['data' => ['message' => $e->getMessage()], 'status' => 500];
        }
    }

    /**
     * Verify OTP and activate user account.
     */
    public function verifyOtp(string $otp): array
    {
        $verificationCode = $this->otpService->getValidOtp($otp);

        if (!$verificationCode) {
            $expiredOtp = $this->otpService->getExpiredOtp($otp);
            if ($expiredOtp) {
                return ['data' => ['message' => 'OTP expired'], 'status' => 401];
            }
            return ['data' => ['message' => 'Invalid OTP'], 'status' => 401];
        }

        $user = User::find($verificationCode->user_id);

        if (!$user) {
            return ['data' => ['message' => 'User not found'], 'status' => 404];
        }

        try {
            DB::beginTransaction();

            $user->is_verified = true;
            $user->email_verified_at = now();
            $user->save();

            $this->otpService->deleteOtp($user->id);

            $token = $user->createToken('authToken')->accessToken;

            DB::commit();

            return ['data' => ['user' => new UserResource($user), 'token' => $token], 'status' => 200];
        } catch (Exception $e) {
            DB::rollBack();
            return ['data' => ['message' => 'OTP verification failed', 'error' => $e->getMessage()], 'status' => 500];
        }
    }

    /**
     * Resend OTP to user's email.
     */
    public function resendOtp(string $email): array
    {
        $user = User::where('email', strtolower($email))->first();
        if (!$user) return ['data' => ['message' => 'User not found'], 'status' => 404];

        $otp = $this->otpService->generateOtp($user->id);
        // $user->notify(new AccountVerificationNotification($user, $otp));
        // return ['data' => ['message' => 'OTP sent to your email'], 'status' => 200];
        return ['data' => ['message' => $otp], 'status' => 200];
    }

    /**
     * Log in the user.
     */
    public function loginUser(string $email, string $password): array
    {
        if (!Auth::attempt(['email' => $email, 'password' => $password])) {
            return ['data' => ['message' => 'Invalid credentials'], 'status' => 400];
        }

        $user = Auth::user();

        if (!$user->is_verified) return ['data' => ['message' => 'Email not verified'], 'status' => 403];
        
        $token = $user->createToken('authToken')->accessToken;

        return ['data' => ['user' => new UserResource($user), 'token' => $token], 'status' => 200];
    }

    /**
     * Request a password reset and send OTP.
     */
    public function requestPasswordReset(string $email): array
    {
        $user = User::where('email', strtolower($email))->first();

        if (!$user) return ['data' => ['message' => 'User not found'], 'status' => 404];
        
        $otp = $this->otpService->generateOtp($user->id);
        // $user->notify(new AccountVerificationNotification($user, $otp));

        return ['data' => ['message' => $otp], 'status' => 200];
        // return ['data' => ['message' => 'OTP sent for password reset'], 'status' => 200];
    }

    /**
     * Reset password using OTP.
     */
    public function resetPassword(string $otp, string $newPassword): array
    {
        $verificationCode = $this->otpService->getValidOtp($otp);

        if (!$verificationCode) {
            $expiredOtp = $this->otpService->getExpiredOtp($otp);
            if ($expiredOtp) {
                return ['data' => ['message' => 'OTP expired'], 'status' => 400];
            }
            return ['data' => ['message' => 'Invalid OTP'], 'status' => 400];
        }

        $user = User::find($verificationCode->user_id);

        if (!$user) return ['data' => ['message' => 'User not found'], 'status' => 404];

        try {
            DB::beginTransaction();

            $user->password = Hash::make($newPassword);
            $user->save();

            $this->otpService->deleteOtp($user->id);

            DB::commit();

            return ['data' => ['message' => 'Password reset successful'], 'status' => 200];
        } catch (Exception $e) {
            DB::rollBack();
            return ['data' => ['message' => 'Password reset failed', 'error' => $e->getMessage()], 'status' => 500];
        }
    }

    /**
     * Log out the user.
     */
    public function logoutUser(): array
    {
        try {
            $user = Auth::user();
            $user->tokens->each(function ($token) {
                $token->delete();
            });

            return ['data' => ['message' => 'Successfully logged out'], 'status' => 200];
        } catch (Exception $e) {
            return ['data' => ['message' => 'Logout failed', 'error' => $e->getMessage()], 'status' => 500];
        }
    }
}
