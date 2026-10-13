<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\LoyaltyRuleRepositoryInterface;
use App\Contracts\Repositories\TenantMembershipPlanRepositoryInterface;
use App\Domain\Membership\Actions\ArchiveAdminMembershipPlan;
use App\Domain\Membership\Actions\CreateAdminMembershipPlan;
use App\Domain\Membership\Actions\CreateAdminMultiplierRule;
use App\Domain\Membership\Actions\DeleteAdminMultiplierRule;
use App\Domain\Membership\Actions\GetAdminLoyaltyConfig;
use App\Domain\Membership\Actions\GetAdminMembershipPlans;
use App\Domain\Membership\Actions\GetAdminMembershipStats;
use App\Domain\Membership\Actions\GetAdminMembershipVersionHistory;
use App\Domain\Membership\Actions\GetAdminMultiplierRules;
use App\Domain\Membership\Actions\UpdateAdminLoyaltyConfig;
use App\Domain\Membership\Actions\UpdateAdminMembershipPlan;
use App\Domain\Membership\Actions\UpdateAdminMultiplierRule;
use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMembershipPlanRequest;
use App\Http\Requests\Admin\StoreMultiplierRuleRequest;
use App\Http\Requests\Admin\UpdateLoyaltyConfigRequest;
use App\Http\Requests\Admin\UpdateMembershipPlanRequest;
use App\Http\Requests\Admin\UpdateMultiplierRuleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMembershipController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        private readonly TenantMembershipPlanRepositoryInterface $planRepository,
        private readonly LoyaltyRuleRepositoryInterface $loyaltyRepository,
    ) {}

    public function plans(Request $request, GetAdminMembershipPlans $action): JsonResponse
    {
        $organization = $this->organization($request);
        $plans = $action->execute($organization->id);

        return response()->json($plans);
    }

    public function storePlan(StoreMembershipPlanRequest $request, CreateAdminMembershipPlan $action): JsonResponse
    {
        $organization = $this->organization($request);
        $plan = $action->execute($organization->id, $request->validated());

        return response()->json($plan, 201);
    }

    public function updatePlan(UpdateMembershipPlanRequest $request, int $id, UpdateAdminMembershipPlan $action): JsonResponse
    {
        $organization = $this->organization($request);
        $plan = $this->planRepository->findByIdAndOrganization($id, $organization->id);
        abort_unless($plan, 404, 'Gói hội viên không tồn tại.');

        $updated = $action->execute($plan, $request->validated());

        return response()->json($updated);
    }

    public function archivePlan(Request $request, int $id, ArchiveAdminMembershipPlan $action): JsonResponse
    {
        $organization = $this->organization($request);
        $plan = $this->planRepository->findByIdAndOrganization($id, $organization->id);
        abort_unless($plan, 404, 'Gói hội viên không tồn tại.');

        $action->execute($plan);

        return response()->json(['message' => 'Đã lưu trữ gói hội viên thành công!']);
    }

    public function loyaltyConfig(Request $request, GetAdminLoyaltyConfig $action): JsonResponse
    {
        $organization = $this->organization($request);
        $config = $action->execute($organization->id);

        return response()->json($config);
    }

    public function updateLoyaltyConfig(UpdateLoyaltyConfigRequest $request, UpdateAdminLoyaltyConfig $action): JsonResponse
    {
        $organization = $this->organization($request);
        $config = $action->execute($organization->id, $request->validated());

        return response()->json($config);
    }

    public function multiplierRules(Request $request, GetAdminMultiplierRules $action): JsonResponse
    {
        $organization = $this->organization($request);
        $rules = $action->execute($organization->id);

        return response()->json($rules);
    }

    public function storeMultiplierRule(StoreMultiplierRuleRequest $request, CreateAdminMultiplierRule $action): JsonResponse
    {
        $organization = $this->organization($request);
        $rule = $action->execute($organization->id, $request->validated());

        return response()->json($rule, 201);
    }

    public function updateMultiplierRule(UpdateMultiplierRuleRequest $request, int $id, UpdateAdminMultiplierRule $action): JsonResponse
    {
        $organization = $this->organization($request);
        $rule = $this->loyaltyRepository->findByIdAndOrganization($id, $organization->id);
        abort_unless($rule, 404, 'Quy tắc nhân điểm không tồn tại.');

        $updated = $action->execute($rule, $request->validated());

        return response()->json($updated);
    }

    public function deleteMultiplierRule(Request $request, int $id, DeleteAdminMultiplierRule $action): JsonResponse
    {
        $organization = $this->organization($request);
        $rule = $this->loyaltyRepository->findByIdAndOrganization($id, $organization->id);
        abort_unless($rule, 404, 'Quy tắc nhân điểm không tồn tại.');

        $action->execute($rule);

        return response()->json(['message' => 'Đã xóa quy tắc nhân điểm thành công!']);
    }

    public function versionHistory(Request $request, GetAdminMembershipVersionHistory $action): JsonResponse
    {
        $organization = $this->organization($request);
        $history = $action->execute($organization->id);

        return response()->json($history);
    }

    public function stats(Request $request, GetAdminMembershipStats $action): JsonResponse
    {
        $organization = $this->organization($request);
        $stats = $action->execute($organization->id);

        return response()->json($stats);
    }
}
