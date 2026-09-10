<?php

declare(strict_types=1);

namespace App\Http\Controllers\Subscription;

use App\Contracts\Repositories\PlatformPlanRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PlatformPlanController extends Controller
{
    public function index(PlatformPlanRepositoryInterface $planRepo): JsonResponse
    {
        $plans = $planRepo->listActive();

        return response()->json([
            'data' => $plans,
        ]);
    }
}
