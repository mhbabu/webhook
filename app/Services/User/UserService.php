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
            $password2 = Str::random(8);
            $password = '12345678';
            $user     = User::create([
                'name'              => $data['name'],
                'email'             => strtolower($data['email']),
                'employee_id'       => $data['employee_id'],
                'max_limit'         => $data['max_limit'],
                'available_scope'   => $data['max_limit'],
                'role_id'           => $data['role_id'],
                'email_verified_at' => now(),
                'mobile'            => $data['mobile'] ?? null,
                'is_verified'       => 1,
                'account_status'    => 'active',
                'password'          => bcrypt($password),
                'email_verified_at'   => now(),
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
    /**
     * Update user profile
     */
    public function updateUserProfile(array $data, $userId): array
    {
        DB::beginTransaction();

        try {
            // Fetch the user
            $user = User::findOrFail($userId);

            // Directly update all fields if provided
            if (isset($data['name'])) {
                $user->name = $data['name'];
            }

            if (isset($data['mobile'])) {
                $user->mobile = $data['mobile'];
            }

            if (isset($data['email'])) {
                $user->email = $data['email'];
            }

            if (isset($data['employee_id'])) {
                $user->employee_id = $data['employee_id'];
            }

            if (isset($data['max_limit'])) {
                $user->max_limit = $data['max_limit'];
                $user->available_scope = $data['max_limit']; // sync available_scope
            }

            if (isset($data['role_id'])) {
                $user->role_id = $data['role_id'];
            }

            // Save changes
            $user->save();

            // Profile picture
            if (!empty($data['profile_picture'])) {
                $user->clearMediaCollection('profile_pictures');
                $user->addMedia($data['profile_picture'])->toMediaCollection('profile_pictures');
            }

            // Platforms
            if (!empty($data['platform_ids']) && is_array($data['platform_ids'])) {
                $user->platforms()->sync(array_unique($data['platform_ids']));
            }

            DB::commit();

            return ['message' => 'User updated successfully', 'status'  => true, 'data' => new UserResource($user)];
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
        // 1️⃣ Find user or return error
        $user = User::find($userId);
        if (! $user) {
            return [
                'message' => 'User not found',
                'status'  => false,
            ];
        }

        // 2️⃣ Prevent self-deletion
        if ($user->id === auth()->id()) {
            return ['message' => 'You cannot delete your own account', 'status'  => false];
        }

        // 3️⃣ Check authenticated user role
        $authRole = strtolower(trim(auth()->user()?->role?->name ?? ''));

        if (! in_array($authRole, ['super admin', 'supervisor'], true)) {
            return ['message' => 'Only Super Admin or Supervisor can take this action', 'status'  => false,];
        }

        // 4️⃣ Delete user and related pivot records in transaction
        DB::beginTransaction();

        try {
            // Detach platforms (pivot table)
            if ($user->platforms()->exists()) {
                $user->platforms()->detach();
            }

            // Delete profile picture collection if exists
            if ($user->hasMedia('profile_pictures')) {
                $user->clearMediaCollection('profile_pictures');
            }

            // Track who deleted
            $user->deleted_by = auth()->id();
            $user->save();

            // Delete the user
            $user->delete();

            DB::commit();

            return ['message' => 'User deleted successfully', 'status' => true];
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['message' => 'Failed to delete user: ' . $e->getMessage(), 'status'  => false];
        }
    }


    /**
     * Log out the user.
     */
    public function logoutUser(): array
    {
        $user = Auth::user();

        // Check for active conversations
        $agentPendingConversations = getAgentActiveConversationsCount($user->id);

        if ($agentPendingConversations > 0) {
            return ['message' => 'Resolve ' . $agentPendingConversations . ' active conversations to log out.', 'status' => false];
        }

        // Update user status
        $user->current_status = UserStatus::OFFLINE->value;
        $user->save();

        // --- Remove user key from Redis ---
        $redisKey = "agent:{$user->id}";
        Redis::del($redisKey);

        // Revoke all tokens
        $user->tokens()->delete();
        return ['message' => 'Logged out successfully', 'status' => true];
    }

    public function validRequest($userId): bool
    {
        return User::where('id', $userId)->where('is_request', 1)->exists();
    }
}
