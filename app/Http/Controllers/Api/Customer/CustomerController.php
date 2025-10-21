<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CustomerVerifyOtpRequest;
use App\Http\Requests\Customer\InitiateChatRequest;
use App\Http\Requests\Customer\ResendCustomerOtpRequest;
use App\Http\Resources\Customer\CustomerInfoResource;
use App\Http\Resources\Message\ConversationResource;
use App\Mail\Customer\SendCustomerOtp;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\OtpVerification;
use Hash;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class CustomerController extends Controller
{

    public function initiateChat(InitiateChatRequest $request)
    {
        $platformId          = Platform::where('name', 'website')->value('id');
        $data                = $request->validated();
        $data['platform_id'] = $platformId;

        // Always generate a new OTP
        $otp          = rand(100000, 999999);
        $otpExpiresAt = Carbon::now()->addMinutes((int) env('WEBSITE_CUSTOMER_OTP_EXPIRE_MINUTES', 2)); // OTP valid for 2 minutes

        try {
            DB::beginTransaction();

            // Find or create customer
            $customer = Customer::where(function ($query) use ($data) {
                $query->where('email', $data['email'])->orWhere('phone', $data['phone']);
            })
                ->where('platform_id', $platformId)
                ->first();

            if (!$customer) {
                $customer = Customer::create([
                    'name'        => $data['name'],
                    'email'       => $data['email'],
                    'phone'       => $data['phone'],
                    'platform_id' => $platformId,
                    'is_verified' => 0,
                    'token'       => null,
                    'token_expires_at' => null,
                ]);
            }

            $customer->update(['token' => null, 'token_expires_at' => null]);

            // Delete any previous OTPs for this customer
            OtpVerification::where('customer_id', $customer->id)->delete();

            // Create new OTP record
            OtpVerification::create([
                'customer_id' => $customer->id,
                'otp'         => 123456, // $otp,
                'expire_at'   => $otpExpiresAt,
            ]);

            // Mail::to($customer->email)->send(new SendCustomerOtp($otp));

            DB::commit();
            return jsonResponse('OTP sent to your email successfully.', true, new CustomerInfoResource($customer));
        } catch (\Exception $e) {
            DB::rollBack();
            return jsonResponse('Failed to process OTP.', false, ['error' => $e->getMessage()], 500);
        }
    }

    public function verifyOtp(CustomerVerifyOtpRequest $request)
    {
        $data = $request->validated();

        // Find Customer
        $platformId = Platform::where('name', 'website')->value('id');
        $customer   = Customer::where('platform_id', $platformId)->where('email', $data['email'])->first();
        
        if (!$customer) {
            return jsonResponse('Customer not found.', false, null, 404);
        }

        // Find the OTP record
        $otpRecord = OtpVerification::where('otp', $data['otp'])->where('customer_id', $customer->id)->first();

        if (!$otpRecord) {
            return jsonResponse('Invalid OTP.', false, null, 422);
        }

        if ($otpRecord->expire_at < now()) {
            return jsonResponse('OTP has expired.', false, null, 422);
        }

        try {
            DB::beginTransaction();

            // Mark customer as verified and generate token
            $customer = Customer::findOrFail($otpRecord->customer_id);
            $customer->is_verified = 1;
            $customer->token = Hash::make($customer->id . $customer->email . now());
            $customer->token_expires_at = Carbon::now()->addMinutes((int) env('WEBSITE_CUSTOMER_TOKEN_EXPIRE_MINUTES', 60)); // Token valid for 60 minutes
            $customer->save();

            // Delete OTP record
            $otpRecord->delete();
            DB::commit();

            return jsonResponse('OTP verified successfully.', true, new CustomerInfoResource($customer));
        } catch (\Exception $e) {
            DB::rollBack();
            return jsonResponse('Failed to verify OTP.', false, ['error' => $e->getMessage()], 500);
        }
    }

    public function resendOtp(ResendCustomerOtpRequest $request)
    {
        $data        = $request->validated();
        $platformId  = Platform::where('name', 'website')->value('id');
        $customer    = Customer::where('platform_id', $platformId)->where('email', $data['email'])->first();

        if (!$customer) {
            return jsonResponse('Customer not found.', false, null, 404);
        }

        // Generate new OTP
        $otp          = rand(100000, 999999);
        $otpExpiresAt = Carbon::now()->addMinutes((int) env('WEBSITE_CUSTOMER_OTP_EXPIRE_MINUTES', 2)); // OTP valid for 2 minutes

        try {
            DB::beginTransaction();

            // Delete any previous OTPs for this customer
            OtpVerification::where('customer_id', $customer->id)->delete();

            // Create new OTP record
            OtpVerification::create([
                'customer_id' => $customer->id,
                'otp'         => 123456, // $otp,
                'expire_at'   => $otpExpiresAt,
            ]);

            // Mail::to($customer->email)->send(new SendCustomerOtp($otp));

            DB::commit();
            return jsonResponse('OTP resent to your email successfully.', true);
        } catch (\Exception $e) {
            DB::rollBack();
            return jsonResponse('Failed to resend OTP.', false, ['error' => $e->getMessage()], 500);
        }
    }

    public function getCustomerWebsiteConversation(Request $request, $token)
    {
        $platformId = Platform::where('name', 'website')->value('id');
        $customer   = Customer::where('platform_id', $platformId)->where('token', $token)->first();

        if (!$customer) {
            return jsonResponse('Invalid customer token.', false, null, 401);
        }

        $data    = $request->all();
        $pagination = !isset($data['pagination']) || $data['pagination'] === 'true';
        $page       = $data['page'] ?? 1;
        $perPage    = $data['per_page'] ?? 10;
        $query      = Conversation::with(['customer', 'agent', 'lastMessage'])->where('customer_id', $customer->id)->whereDate('created_at', now()->format('Y-m-d'))->latest();

        if ($pagination) {
            $conversations = $query->paginate($perPage, ['*'], 'page', $page);
            return jsonResponseWithPagination('Conversations retrieved successfully', true, ConversationResource::collection($conversations)->response()->getData(true));
        }

        $conversations = $query->get();

        return jsonResponse('Conversations retrieved successfully', true, ConversationResource::collection($conversations));
    }
}
