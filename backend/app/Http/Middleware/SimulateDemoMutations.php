<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SimulateDemoMutations
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isDemo() || in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        if ($request->routeIs('verification.verify') || $request->is('api/v1/auth/email/verification-notification')) {
            return response()->json(['message' => 'Đã mô phỏng gửi email xác thực.'], 200)
                ->header('X-Demo-Simulated', 'true');
        }

        $connection = DB::connection();
        $connection->beginTransaction();

        try {
            $response = $next($request);
            $connection->rollBack();

            return $response->header('X-Demo-Simulated', 'true');
        } catch (\Throwable $exception) {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }
}
