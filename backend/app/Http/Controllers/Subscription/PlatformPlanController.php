<?php

declare(strict_types=1);

namespace App\Http\Controllers\Subscription;

use App\Contracts\Repositories\PlatformPlanRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\PlatformPlanLifecycleRequest;
use App\Http\Requests\Subscription\StorePlatformPlanRequest;
use App\Http\Requests\Subscription\UpdatePlatformPlanRequest;
use App\Http\Resources\Platform\PlatformPlanResource;
use App\Models\PlatformPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformPlanController extends Controller
{
    private const PLAN_FIELDS = [
        'code', 'name', 'price', 'currency', 'billing_cycle', 'trial_days', 'trial_enabled',
        'max_venues', 'max_courts', 'max_staff', 'features', 'terms_version', 'terms_text',
    ];

    public function catalog(PlatformPlanRepositoryInterface $planRepo): JsonResponse
    {
        return response()->json([
            'data' => PlatformPlanResource::collection($planRepo->listActive()),
        ]);
    }

    public function index(PlatformPlanRepositoryInterface $planRepo): JsonResponse
    {
        return response()->json([
            'data' => PlatformPlanResource::collection($planRepo->listAll()),
        ]);
    }

    public function show(int $id, PlatformPlanRepositoryInterface $planRepo): JsonResponse
    {
        $plan = $planRepo->findById($id);
        abort_unless($plan, 404, 'Gói dịch vụ nền tảng không tồn tại.');

        return response()->json(['data' => PlatformPlanResource::make($plan)]);
    }

    public function store(
        StorePlatformPlanRequest $request,
        PlatformPlanRepositoryInterface $planRepo
    ): JsonResponse {
        $attributes = $request->validated();
        if ($planRepo->findByCode($attributes['code'])) {
            abort(409, 'Mã gói đã tồn tại. Hãy tạo phiên bản mới từ gói hiện có.');
        }

        $plan = $planRepo->create(array_merge($attributes, [
            'version' => 1,
            'status' => 'draft',
            'is_active' => false,
        ]));

        return response()->json([
            'data' => PlatformPlanResource::make($plan),
            'message' => 'Đã tạo bản nháp gói dịch vụ.',
        ], 201);
    }

    public function update(
        UpdatePlatformPlanRequest $request,
        int $id,
        PlatformPlanRepositoryInterface $planRepo
    ): JsonResponse {
        $input = $request->validated();
        $expectedVersion = (int) $input['expected_version'];
        unset($input['expected_version']);

        $plan = DB::transaction(function () use ($id, $expectedVersion, $input, $planRepo) {
            $current = $planRepo->findByIdForUpdate($id);
            abort_unless($current, 404, 'Gói dịch vụ nền tảng không tồn tại.');

            if ($current->version !== $expectedVersion) {
                abort(409, 'Phiên bản gói đã thay đổi. Vui lòng tải lại trước khi sửa.');
            }

            $latest = $planRepo->findLatestByCodeForUpdate($current->code);
            if (! $latest || $latest->id !== $current->id) {
                abort(409, 'Chỉ có thể sửa phiên bản mới nhất của gói.');
            }

            if ($current->status === 'draft') {
                return $planRepo->update($current, $input);
            }

            return $planRepo->create(array_merge($current->only(self::PLAN_FIELDS), $input, [
                'version' => $latest->version + 1,
                'status' => 'draft',
                'is_active' => false,
                'published_at' => null,
                'retired_at' => null,
            ]));
        });

        return response()->json([
            'data' => PlatformPlanResource::make($plan),
            'message' => $plan->status === 'draft' && $plan->version > $expectedVersion
                ? 'Đã tạo phiên bản nháp mới; phiên bản đang công bố vẫn được giữ nguyên.'
                : 'Đã cập nhật bản nháp gói dịch vụ.',
        ]);
    }

    public function publish(
        PlatformPlanLifecycleRequest $request,
        int $id,
        PlatformPlanRepositoryInterface $planRepo
    ): JsonResponse {
        $expectedVersion = (int) $request->validated('expected_version');

        $plan = DB::transaction(function () use ($id, $expectedVersion, $planRepo) {
            $current = $planRepo->findByIdForUpdate($id);
            abort_unless($current, 404, 'Gói dịch vụ nền tảng không tồn tại.');

            if ($current->version !== $expectedVersion) {
                abort(409, 'Phiên bản gói đã thay đổi. Vui lòng tải lại trước khi công bố.');
            }

            $latest = $planRepo->findLatestByCodeForUpdate($current->code);
            if (! $latest || $latest->id !== $current->id || $current->status !== 'draft') {
                abort(409, 'Chỉ có thể công bố bản nháp mới nhất của gói.');
            }

            $this->validateCommercialConfiguration($current);
            $now = now();
            foreach ($planRepo->listByCodeForUpdate($current->code) as $previous) {
                if ($previous->status === 'published' && $previous->id !== $current->id) {
                    $planRepo->update($previous, [
                        'status' => 'retired',
                        'is_active' => false,
                        'retired_at' => $now,
                    ]);
                }
            }

            return $planRepo->update($current, [
                'status' => 'published',
                'is_active' => true,
                'published_at' => $now,
                'retired_at' => null,
            ]);
        });

        return response()->json([
            'data' => PlatformPlanResource::make($plan),
            'message' => 'Đã công bố gói dịch vụ.',
        ]);
    }

    public function retire(
        PlatformPlanLifecycleRequest $request,
        int $id,
        PlatformPlanRepositoryInterface $planRepo
    ): JsonResponse {
        $expectedVersion = (int) $request->validated('expected_version');

        $plan = DB::transaction(function () use ($id, $expectedVersion, $planRepo) {
            $current = $planRepo->findByIdForUpdate($id);
            abort_unless($current, 404, 'Gói dịch vụ nền tảng không tồn tại.');

            if ($current->version !== $expectedVersion) {
                abort(409, 'Phiên bản gói đã thay đổi. Vui lòng tải lại trước khi ngừng cung cấp.');
            }

            $latest = $planRepo->findLatestByCodeForUpdate($current->code);
            if (! $latest || $latest->id !== $current->id) {
                abort(409, 'Chỉ có thể ngừng cung cấp phiên bản mới nhất của gói.');
            }

            if ($current->status !== 'retired') {
                return $planRepo->update($current, [
                    'status' => 'retired',
                    'is_active' => false,
                    'retired_at' => now(),
                ]);
            }

            return $current;
        });

        return response()->json([
            'data' => PlatformPlanResource::make($plan),
            'message' => 'Đã ngừng cung cấp gói mới; lịch sử gói và subscription hiện có được giữ nguyên.',
        ]);
    }

    private function validateCommercialConfiguration(PlatformPlan $plan): void
    {
        $errors = [];

        if ($plan->price === null || ($plan->price <= 0 && ! $plan->trial_enabled)) {
            $errors['price'] = 'Cần cấu hình giá hợp lệ trước khi công bố gói.';
        }
        if (empty($plan->billing_cycle)) {
            $errors['billing_cycle'] = 'Cần cấu hình chu kỳ thanh toán trước khi công bố gói.';
        }
        if (empty($plan->terms_version) || empty($plan->terms_text)) {
            $errors['terms_text'] = 'Cần có điều khoản được phê duyệt trước khi công bố gói.';
        }
        if ($plan->trial_enabled && $plan->trial_days <= 0) {
            $errors['trial_days'] = 'Thời hạn dùng thử phải lớn hơn 0 khi bật dùng thử.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
