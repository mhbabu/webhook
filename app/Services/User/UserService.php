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
    public function getUserList($data)
    {
        $page    = $data['page'] ?? 1;
        $perPage = $data['per_page'] ?? 10;
        $users   = User::paginate($perPage, ['*'], 'page', $page);

        return ['message' => 'User list retrieved successfully', 'status' => true, 'data' => UserResource::collection($users)->response()->getData(true)];
    }

    /**
     * Get a list of former users.
     */
    public function getFormerUserList($data)
    {
        $page    = $data['page'] ?? 1;
        $perPage = $data['per_page'] ?? 10;
        $users   = User::onlyTrashed()->paginate($perPage, ['*'], 'page', $page);

        return ['message' => 'Former user list retrieved successfully', 'status' => true, 'data' => UserResource::collection($users)->response()->getData(true)];
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

            if (isset($data['profile_picture'])) {
                $user->addMedia($data['profile_picture'])->toMediaCollection('profile_pictures');
            }

            $user->platforms()->sync($data['platform_ids']); // Sync platforms
            // $otp = $this->otpService->generateOtp($user->id);
            // $user->notify(new AccountVerificationNotification($otp));

            DB::commit();

            return ['message' => 'User created successfully', 'password' => $password, 'status' => true];
        } catch (Exception $e) {
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

        if (!$user) {
            return ['message' => 'User not found', 'status' => false];
        }

        if (!$this->validRequest($user->id)) {
            return ['message' => 'Invalid request', 'status' => false];
        }

        return ['message' => 'OTP verified successfully', 'status' => true];
    }

    /**
     * Resend OTP to user's email.
     */
    public function resendOtp(string $email): array
    {
        $user = User::where('email', strtolower($email))->first();
        if (!$user) return ['message' => 'User not found', 'status' => false];
        if (!$this->validRequest($user->id)) return ['message' => 'Invalid request', 'status' => false];

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
        $user->update(['is_request' => 1]);
        $otp = $this->otpService->generateOtp($user->id);
        // $user->notify(new PasswordResetNotification($otp));

        return ['message' => 'OTP sent to your email', 'otp' => $otp, 'status' => true];
    }

    /**
     * Reset password using OTP.
     */
    public function resetPassword($data): array
    {
        $verificationCode = $this->otpService->getValidOtp($data['otp']);

        if (!$verificationCode) {
            $expiredOtp = $this->otpService->getExpiredOtp($data['otp']);
            $message    = $expiredOtp ? 'OTP expired' : 'Invalid OTP';
            return ['message' => $message, 'status' => false];
        }

        $user = User::find($verificationCode->user_id);

        if (!$user) return ['message' => 'User not found', 'status' => false];
        if (!$this->validRequest($user->id)) return ['message' => 'Invalid request', 'status' => false];

        try {
            DB::beginTransaction();

            $user->password = bcrypt($data['new_password']);
            $user->is_request = 0;
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
     * Update password using OTP.
     */
    public function updatePassword($data): array
    {
        $user = Auth::user();

        if (!Hash::check($data['current_password'], $user->password)) {
            return ['message' => 'The current password you provided is incorrect.', 'status' => false];
        }

        if ($data['current_password'] === $data['new_password']) {
            return ['message' => 'The new password cannot be the same as the current password.', 'status' => false];
        }

        $user->password = bcrypt($data['new_password']);
        $user->save();

        return ['message' => 'Your password has been updated successfully.', 'status' => true];
    }

    /**
     * Log out the user.
     */
    public function getMeInfo(): array
    {
        $user = Auth::user();
        return ['message' => 'User retrieved successfully', 'status' => true, 'data' => new UserResource($user)];
    }

    /**
     * Get User by ID
     */
    public function getUserById(User $user): array
    {
        return ['message' => 'User retrieved successfully', 'status' => true, 'data' => new UserResource($user)];
    }

    /**
     * Update User
     */
    public function updateUserProfile(User $user, array $data): array
    {
        $user->update($data);
        return ['message' => 'User updated successfully', 'status' => true];
    }

    /**
     * Delete User
     */
    public function deleteUser(User $user): array
    {
        if (!$user) {
            return ['message' => 'User not found', 'status' => false];
        }

        if ($user->id === auth()->id()) {
            return ['message' => 'You cannot delete your own account', 'status' => false];
        }

        if ($user->type === 'supervisor') {
            return ['message' => 'You cannot delete a supervisor', 'status' => false];
        }

        $user->delete();
        return ['message' => 'User deleted successfully', 'status' => true];
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

    public function validRequest($userId): bool
    {
        return User::where('id', $userId)->where('is_request', 1)->exists();
    }
}
