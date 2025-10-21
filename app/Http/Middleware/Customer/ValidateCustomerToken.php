<?php

namespace App\Http\Middleware\Customer;

use Closure;
use Illuminate\Http\Request;
use App\Models\Customer;

class ValidateCustomerToken
{
    public function handle(Request $request, Closure $next)
    {
        // ✅ Read token from header
        $authorizationHeader = $request->header('Authorization');
        $token = null;

        if ($authorizationHeader && preg_match('/Bearer\s+(.*)$/i', $authorizationHeader, $matches)) {
            $token = $matches[1];
        }

        if (!$token) {
            return response()->json(['status' => false, 'message' => 'Authorization token not provided.'], 401);
        }

        $customer = Customer::where('token', $token)->first();

        if (!$customer) {
            return response()->json(['status' => false, 'message' => 'Invalid customer token.'], 401);
        }

        // ✅ Check expiry
        if ($customer->token_expires_at && now()->greaterThan($customer->token_expires_at)) {
            return response()->json(['status' => false, 'message' => 'Token has expired'], 401);
        }

        // ✅ Attach the customer to the request
        $request->merge(['auth_customer' => $customer]);

        return $next($request);
    }
}
