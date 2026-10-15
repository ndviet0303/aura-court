<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountNotRestricted
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // BR-23: a session signed in before the account was locked never comes back, even after an unlock.
        if ($user?->sessions_revoked_at && $request->hasSession()
            && (int) $request->session()->get('auth_at', 0) < $user->sessions_revoked_at->getTimestamp()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return response()->json(['message' => 'Phiên đăng nhập đã hết hiệu lực. Vui lòng đăng nhập lại.'], 401);
        }

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
