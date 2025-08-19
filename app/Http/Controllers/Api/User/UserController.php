<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\CreateNewAgenRequest;
use App\Http\Requests\User\LoginRequest;
use App\Http\Requests\User\ResetOtpRequest;
use App\Http\Requests\User\ResetPasswordRequest;
use App\Http\Requests\User\StoreResetPassword;
use App\Http\Requests\User\UpdatePasswordRequest;
use App\Http\Requests\User\UpdateUserProfileRequest;
use App\Http\Requests\User\ValidateOtpRequest;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Get a list of users.
     */
    public function index(Request $request)
    {
        $response = $this->userService->getUserList($request->all());
        return $this->jsonResponse($response['message'], $response['status'], $response['data']);
    }

    /**
     * Get a list of former users.
     */
    public function getFormerUserList(Request $request)
    {
        $response = $this->userService->getFormerUserList($request->all());
        return $this->jsonResponse($response['message'], $response['status'], $response['data']);
    }

    /**
     * Register a user and send OTP.
     */
    public function store(CreateNewAgenRequest $request)
    {
        $response = $this->userService->createUser($request->validated());
        return $this->passwordjsonResponse($response['message'], $response['status'], $response['password']);
        // return $this->jsonResponse($response['message'], $response['status']);
    }

    /**
     * Verify OTP and activate user account.
     */
    public function verifyOtp(ValidateOtpRequest $request)
    {
        $response = $this->userService->verifyOtp($request->otp);
        return $this->jsonResponse($response['message'], $response['status'], $response['data'] ?? null);
    }

    /**
     * Resend OTP to the user's email.
     */
    public function resendOtp(ResetOtpRequest $request)
    {
        $response = $this->userService->resendOtp($request->email);
        return $this->otpjsonResponse($response['message'], $response['status'], $response['otp'] ?? null);
        // return $this->jsonResponse($response['message'], $response['status']);
    }

    /**
     * Log in the user.
     */
    public function login(LoginRequest $request)
    {
        $response = $this->userService->loginUser($request->only(['email', 'password']));
        return $this->jsonResponse($response['message'], $response['status'], $response['data'] ?? null);
    }

    /**
     * Request a password reset and send OTP.
     */
    public function passwordResetRequest(ResetPasswordRequest $request)
    {
        $response = $this->userService->requestPasswordReset($request->email);
        return $this->otpjsonResponse($response['message'], $response['status'], $response['otp']);
        // return $this->jsonResponse($response['message'], $response['status']);
    }

    /**
     * Reset password using OTP.
     */
    public function resetPassword(StoreResetPassword $request)
    {
        $response = $this->userService->resetPassword($request->all());
        return $this->jsonResponse($response['message'], $response['status']);
    }

    /**
     * Update Password
     */
    public function updatePassword(UpdatePasswordRequest $request)
    {
        $response = $this->userService->updatePassword($request->all());
        return $this->jsonResponse($response['message'], $response['status']);
    }

    /**
     * Get Current Auth UserInfo
     */
    public function getMe()
    {
        $response = $this->userService->getMeInfo();
        return response()->json(['status' => $response['status'], 'message' => $response['message'], 'user' => $response['data']], 200);
    }

    /**
     * Show User
     */
    public function show(User $user)
    {
        $response = $this->userService->getUserById($user);
        return $this->jsonResponse($response['message'], $response['status'], $response['data']);
    }

    /**
     * Update User
     */
    public function update(UpdateUserProfileRequest $request, $userId)
    {
        $data     = $request->validated(); // ensures $data is an array of only validated inputs
        $response = $this->userService->updateUserProfile($data, $userId);

        return $this->jsonResponse($response['message'], $response['status'], $response['data'] ?? null);
    }

    /**
     * Delete User
     */
    public function destroy($userId)
    {
        $response = $this->userService->deleteUser($userId);
        return $this->jsonResponse($response['message'], $response['status']);
    }

    /**
     * Log out the user.
     */
    public function logout()
    {
        $response = $this->userService->logoutUser();
        return $this->jsonResponse($response['message'], $response['status']);
    }

    /**
     * Return JSON response.
     */
    private function jsonResponse(string $message, bool $status, $data = null)
    {
        return response()->json(['status' => $status, 'message' => $message, 'data' => $data], $status ? 200 : 400);
    }

    // Using this respone like a demo for otp visualization
    private function otpjsonResponse(string $message, bool $status, string $otp = null, $data = null)
    {
        return response()->json(['status' => $status, 'message' => $message, 'otp' => $otp, 'data' => $data], $status ? 200 : 400);
    }

    private function passwordjsonResponse(string $message, bool $status, string $password = null, $data = null)
    {
        return response()->json(['status' => $status, 'message' => $message, 'password' => $password, 'data' => $data], $status ? 200 : 400);
    }
}
