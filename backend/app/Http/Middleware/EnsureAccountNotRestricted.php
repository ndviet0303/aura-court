<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountNotRestricted
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isRestricted()) {
            // Allow checking own profile status and logging out
            if ($request->is('*/auth/me') || $request->is('*/auth/logout')) {
                return $next($request);
            }

            return response()->json([
                'message' => 'Tài khoản của bạn đã bị khóa hoặc hạn chế quyền truy cập.',
                'error' => 'account_restricted',
            ], 403);
        }

        return $next($request);
    }
}
