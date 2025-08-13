<?php

namespace App\Services\User;

use App\Http\Resources\User\UserResource;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Exception;

class UserService
{
    protected $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Register a new user and send OTP.
     */
    public function createUser(array $data): array
    {
        DB::beginTransaction();

        try {

            // Generate a random password
            $password = Str::random(8); 
            $user     = User::create([
                'name'              => $data['name'],
                'email'             => strtolower($data['email']),
                'employee_id'       => $data['employee_id'],
                'max_limit'         => $data['max_limit'],
                'email_verified_at' => now(),
                'is_verified'       => 1,
                'account_status'    => 'active',
                'password'          => bcrypt($password),
            ]);

            $user->platforms()->sync($data['platform_ids']); // Sync platforms
            // $otp = $this->otpService->generateOtp($user->id);
            // $user->notify(new AccountVerificationNotification($otp));

            DB::commit();

            return ['message' => 'User created successfully', 'password' => $password, 'status' => true];
        } catch (Exception $e) {
            info($e->getMessage());
            DB::rollBack();
            return ['message' => 'User creation failed: ' . $e->getMessage(), 'status' => false];
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
            $message = $expiredOtp ? 'OTP expired' : 'Invalid OTP';
            return ['message' => $message, 'status' => false];
        }

        $user = User::find($verificationCode->user_id);

        if (!$user) return ['message' => 'User not found', 'status' => false];

        try {
            DB::beginTransaction();

            $user->is_verified       = true;
            $user->is_active         = true;
            $user->email_verified_at = now();
            $user->save();

            $this->otpService->deleteOtp($user->id);

            DB::commit();

            $token = $user->createToken('authToken')->accessToken;
            return ['message' => 'OTP verified successfully', 'status' => true, 'data' => ['user' => new UserResource($user), 'token' => $token]];
        } catch (Exception $e) {
            DB::rollBack();
            return ['message' => 'OTP verification failed: ' . $e->getMessage(), 'status' => false];
        }
    }

    /**
     * Resend OTP to user's email.
     */
    public function resendOtp(string $email): array
    {
        $user = User::where('email', strtolower($email))->first();
        if (!$user) return ['message' => 'User not found', 'status' => false];

        if(!$user->reset_password_request) return ['message'=> 'Invalid request', 'status'=> false];

        $otp = $this->otpService->generateOtp($user->id);
        // $user->notify(new AccountVerificationNotification($otp));

        return ['message' => 'OTP resent to your email', 'otp' => $otp, 'status' => true];
    }

    /**
     * Log in the user.
     */
    public function loginUser(array $credentials): array
    {
        if (!Auth::attempt($credentials)) {
            return ['message' => 'Invalid credentials', 'status' => false];
        }

        $user = Auth::user();

        if (!$user->is_verified) return ['message' => 'Email not verified', 'status' => false];

        $token = $user->createToken('auth_token')->plainTextToken;

        return ['message' => 'Login successful', 'status' => true, 'data' => ['user' => new UserResource($user), 'token' => $token]];
    }

    /**
     * Request a password reset and send OTP.
     */
    public function requestPasswordReset(string $email): array
    {
        $user = User::where('email', strtolower($email))->first();

        if (!$user) return ['message' => 'User not found', 'status' => false];
        $user->update(['reset_password_request' => 1]);
        $otp = $this->otpService->generateOtp($user->id);
        // $user->notify(new PasswordResetNotification($otp));

        return ['message' => 'OTP sent to your email', 'otp' => $otp, 'status' => true];
    }

    /**
     * Reset password using OTP.
     */
    public function resetPassword(string $otp, string $newPassword): array
    {
        $verificationCode = $this->otpService->getValidOtp($otp);

        if (!$verificationCode) {
            $expiredOtp = $this->otpService->getExpiredOtp($otp);
            $message = $expiredOtp ? 'OTP expired' : 'Invalid OTP';
            return ['message' => $message, 'status' => false];
        }

        $user = User::find($verificationCode->user_id);

        if (!$user) return ['message' => 'User not found', 'status' => false];

        try {
            DB::beginTransaction();

            $user->password = Hash::make($newPassword);
            $user->reset_password_request = 0;
            $user->save();

            $this->otpService->deleteOtp($user->id);

            DB::commit();

            return ['message' => 'Password reset successful', 'status' => true];
        } catch (Exception $e) {
            DB::rollBack();
            return ['message' => 'Password reset failed: ' . $e->getMessage(), 'status' => false];
        }
    }

    /**
     * Log out the user.
     */
    public function getMeInfo(): object
    {
        $user = Auth::user();
        return  new UserResource($user);
    }

    /**
     * Log out the user.
     */
    public function logoutUser(): array
    {
        // request()->user()->currentAccessToken()->delete();
        request()->user()->tokens()->delete();
        return ['message' => 'Logged out successfully', 'status' => true];
    }
}
