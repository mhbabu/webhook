<?php

namespace App\Services\User;

use App\Enums\UserStatus;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\Redis;

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
        $pagination = !isset($data['pagination']) || $data['pagination'] === 'true' ? true : false;
        $page       = $data['page'] ?? 1;
        $perPage    = $data['per_page'] ?? 10;
        $searchText = $data['search'] ?? null;
        $searchBy   = $data['search_by'] ?? 'name';
        $sortBy     = $data['sort_by'] ?? 'id';
        $sortOrder  = $data['sort_order'] ?? 'asc';

        $query = User::query();

        if ($searchText && $searchBy) {
            $query->where($searchBy, 'like', "%{$searchText}%");
        }

        $query->orderBy($sortBy, $sortOrder);

        if ($pagination) {
            $users = $query->paginate($perPage, ['*'], 'page', $page);
            return jsonResponseWithPagination('User list retrieved successfully', true, UserResource::collection($users)->response()->getData(true));
        }

        return jsonResponse('User list retrieved successfully', true, UserResource::collection($query->get()));
    }

    /**
     * Get a list of former users.
     */

    public function getFormerUserList($data)
    {
        $pagination = !isset($data['pagination']) || $data['pagination'] === 'true' ? true : false;
        $page       = $data['page'] ?? 1;
        $perPage    = $data['per_page'] ?? 10;
        $searchText = $data['search'] ?? null;
        $searchBy   = $data['search_by'] ?? 'name';
        $sortBy     = $data['sort_by'] ?? 'id';
        $sortOrder  = $data['sort_order'] ?? 'asc';

        $query = User::onlyTrashed();

        if ($searchText && $searchBy) {
            $query->where($searchBy, 'like', "%{$searchText}%");
        }

        $query->orderBy($sortBy, $sortOrder);

        if ($pagination) {
            $users = $query->paginate($perPage, ['*'], 'page', $page);
            return jsonResponseWithPagination('User list retrieved successfully', true, UserResource::collection($users)->response()->getData(true));
        }

        return jsonResponse('User list retrieved successfully', true, UserResource::collection($query->get()));
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
                'role_id'           => $data['role_id'],
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
    public function getUserById($userId): array
    {
        $user = User::find($userId);
        if (!$user) {
            return ['message' => 'User not found', 'status' => false];
        }

        return ['message' => 'User retrieved successfully', 'status' => true, 'data' => new UserResource($user)];
    }

    /**
     * Update User
     */
    public function updateUserProfile(array $data, $userId): array
    {
        $user = User::find($userId);
        if (!$user) {
            return ['message' => 'User not found', 'status' => false];
        }

        DB::beginTransaction();

        try {
            // Update basic fields for all users
            if (isset($data['name'])) {
                $user->name = $data['name'];
            }

            if (isset($data['mobile'])) {
                $user->mobile = $data['mobile'];
            }

            // Get authenticated user's role name
            $role = auth()->user()->role->name ?? null;

            // Only Super Admin, Admin, Supervisor can update these fields
            if (in_array($role, ['Super Admin', 'Admin', 'Supervisor'])) {
                $user->email       = $data['email'] ?? $user->email;
                $user->employee_id = $data['employee_id'] ?? $user->employee_id;
                $user->max_limit   = $data['max_limit'] ?? $user->max_limit;
                $user->role_id     = $data['role_id'] ?? $user->role_id;
            }

            // Save changes
            $user->save();

            // Handle profile picture
            if (isset($data['profile_picture'])) {
                $user->clearMediaCollection('profile_pictures'); // remove old picture
                $user->addMedia($data['profile_picture'])->toMediaCollection('profile_pictures');
            }

            // Sync platforms if provided
            if (!empty($data['platform_ids']) && is_array($data['platform_ids'])) {
                $user->platforms()->sync(array_unique($data['platform_ids']));
            }

            DB::commit();

            return ['message' => 'User updated successfully', 'status'  => true, 'data'    => new UserResource($user)];
        } catch (\Exception $e) {
            DB::rollBack();

            return ['message' => 'User update failed: ' . $e->getMessage(), 'status'  => false];
        }
    }


    /**
     * Delete User
     */
    public function deleteUser($userId): array
    {
        $user = User::find($userId);
        if (!$user) {
            return ['message' => 'User not found', 'status' => false];
        }

        if ($user->id === auth()->id()) {
            return ['message' => 'You cannot delete your own account', 'status' => false];
        }

        if ($user->role_id != 1) {
            return ['message' => 'You have no permission to take this action', 'status' => false];
        }

        $user->delete();
        return ['message' => 'User deleted successfully', 'status' => true];
    }

    /**
     * Log out the user.
     */
    public function logoutUser(): array
    {
        $user = Auth::user();

        // Update user status
        $user->current_status = UserStatus::OFFLINE->value;
        $user->save();

        // --- Redis Update using helper ---
        $this->updateUserInRedis($user);

        // Revoke all tokens
        $user->tokens()->delete();
        return ['message' => 'Logged out successfully', 'status' => true];
    }

    public function validRequest($userId): bool
    {
        return User::where('id', $userId)->where('is_request', 1)->exists();
    }

    private function updateUserInRedis($user): void
    {
        $redisKey = "agent:{$user->id}";

        $agentData = [
            "AGENT_ID"        => $user->id,
            "AGENT_TYPE"      => $user->agent_type ?? 'NORMAL',
            "STATUS"          => $user->current_status ?? 'inactive',
            "MAX_SCOPE"       => $user->max_limit ?? 0,
            "AVAILABLE_SCOPE" => $user->available_limit ?? ($user->max_limit ?? 0),
            "CONTACT_TYPE"    => json_encode($user->contact_type ?? []),
            "SKILL"           => json_encode(
                $user->platforms()->pluck('name')->map(fn($name) => strtolower($name))->toArray()
            ),
            "BUSYSINCE"        => optional($user->changed_at)->format('Y-m-d H:i:s') ?? '',
        ];

        // Save as Redis Hash
        Redis::hMSet($redisKey, $agentData);
    }
}
