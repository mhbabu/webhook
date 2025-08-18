<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = auth()->user();

        if (!$user) {
            return jsonResponse(false, 'Unauthenticated or invalid token', 401);
        }

        $roleName = $user->role->name ?? null;

        if (! in_array($roleName, $roles, true)) {
            return jsonResponse(false, 'Unauthorized action', 403);
        }

        return $next($request);
    }
}
