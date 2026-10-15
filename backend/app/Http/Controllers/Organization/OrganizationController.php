<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\InviteStaffRequest;
use App\Http\Requests\Organization\RemoveStaffMemberRequest;
use App\Http\Requests\Organization\UpdateStaffMemberRequest;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Services\Infrastructure\FeatureFlagService;
use App\Services\Infrastructure\TenantContext;
use App\Services\Organization\OrganizationInvitationService;
use App\Services\Organization\StaffMembershipService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrganizationController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly FeatureFlagService $featureFlags,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isPlatformAdmin()) {
            $organizations = Organization::withCount(['venues', 'members'])->get();
        } else {
            $organizations = $user->organizations()->withCount(['venues', 'members'])->get();
        }

        return response()->json(['organizations' => $organizations]);
    }

    public function show(Request $request, Organization $organization): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isPlatformAdmin() || $organization->hasMember($user->id), 403);

        $organization->load(['venues.courts', 'members.user']);

        return response()->json(['organization' => $organization]);
    }

    public function capabilities(Request $request, ?Organization $organization = null): JsonResponse
    {
        $targetOrg = $organization ?? $this->tenantContext->getOrganization();
        if (! $targetOrg && $request->has('organization_id')) {
            $targetOrg = Organization::find((int) $request->input('organization_id'));
        }

        $user = $request->user();
        if ($targetOrg) {
            if (! ($user && ($user->isPlatformAdmin() || $targetOrg->hasMember($user->id)))) {
                abort(response()->json([
                    'message' => 'Unauthorized organization access.',
                    'code' => 'ORGANIZATION_ACCESS_DENIED',
                ], 403));
            }
        }
        $orgId = $targetOrg?->id;
        $capabilities = $this->featureFlags->effectiveCapabilities($orgId);

        $user = $request->user();
        $role = $orgId && $user ? $user->roleInOrganization($orgId) : null;
        $isPlatformAdmin = $user ? $user->isPlatformAdmin() : false;

        return response()->json([
            'organization_id' => $orgId,
            'capabilities' => $capabilities,
            'role' => $role,
            'is_platform_admin' => $isPlatformAdmin,
        ]);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasRoleInOrganization($organization->id, [OrganizationMember::ROLE_ORGANIZATION_OWNER]),
            403
        );

        if ($request->has('cancellation_grace_hours') || array_key_exists('cancellation_grace_hours', (array) $request->input('settings', []))) {
            throw ValidationException::withMessages([
                'cancellation_grace_hours' => 'Cấu hình thời gian hủy đặt sân không còn được hỗ trợ.',
            ]);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:160',
            'tax_code' => 'nullable|string|max:50',
            'settings' => 'nullable|array',
            'support_email' => 'nullable|string|max:160',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'brand_color' => 'nullable|string|max:50',
            'logo_url' => 'nullable|string|max:255',
            'auto_approve_booking' => 'nullable|boolean',
            'resale_fee_percent' => 'nullable|numeric|min:0|max:100',
            'cancellation_grace_hours' => 'prohibited',
            'settings.cancellation_grace_hours' => 'prohibited',
            'currency' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
        ]);

        $settings = $organization->settings ?? [];
        if (isset($data['settings']) && is_array($data['settings'])) {
            $settings = array_merge($settings, $data['settings']);
        }

        $settingKeys = [
            'support_email',
            'phone',
            'address',
            'brand_color',
            'logo_url',
            'auto_approve_booking',
            'resale_fee_percent',
            'currency',
            'timezone',
        ];

        foreach ($settingKeys as $key) {
            if (array_key_exists($key, $data)) {
                $settings[$key] = $data[$key];
            }
        }

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (array_key_exists('tax_code', $data)) {
            $updateData['tax_code'] = $data['tax_code'];
        }
        $updateData['settings'] = $settings;

        try {
            $organization->update($updateData);
        } catch (UniqueConstraintViolationException) {
            // BR-15: the unique tax code index is the only guard that holds under concurrent writes.
            throw ValidationException::withMessages(['tax_code' => 'Mã số thuế đã thuộc về một tổ chức khác.']);
        }

        return response()->json(['organization' => $organization->fresh(['venues.courts', 'members.user'])]);
    }

    public function members(Request $request, Organization $organization): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasRoleInOrganization($organization->id, [
                OrganizationMember::ROLE_ORGANIZATION_OWNER,
            ]),
            403
        );

        $members = $organization->members()->with('user')->get();

        return response()->json(['members' => $members]);
    }

    public function updateMemberRole(UpdateStaffMemberRequest $request, Organization $organization, OrganizationMember $member, StaffMembershipService $staff): JsonResponse
    {
        $member = $staff->update($organization, $request->user(), $member, $request->validated(), $request->ip());

        return response()->json(['member' => $member->fresh()?->load('user')]);
    }

    public function removeMember(RemoveStaffMemberRequest $request, Organization $organization, OrganizationMember $member, StaffMembershipService $staff): JsonResponse
    {
        $staff->terminate($organization, $request->user(), $member, $request->ip());

        return response()->json(['message' => 'Đã chấm dứt tư cách nhân viên.']);
    }

    public function invitations(Request $request, Organization $organization): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasRoleInOrganization($organization->id, [
                OrganizationMember::ROLE_ORGANIZATION_OWNER,
            ]),
            403
        );

        // BR-18: an unanswered invitation past its expiry is shown, and filtered, as expired.
        $organization->invitations()->where('status', OrganizationInvitation::STATUS_PENDING)->where('expires_at', '<=', now())
            ->update(['status' => OrganizationInvitation::STATUS_EXPIRED]);
        $query = $organization->invitations()->with(['inviter:id,name,email', 'venue:id,name']);
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $invitations = $query->latest()->get();

        return response()->json(['invitations' => $invitations]);
    }

    public function invite(InviteStaffRequest $request, Organization $organization, OrganizationInvitationService $invitations): JsonResponse
    {
        return response()->json(['invitation' => $invitations->invite($organization, $request->user(), $request->validated(), $request->ip())], 201);
    }

    public function revokeInvitation(Request $request, Organization $organization, OrganizationInvitation $invitation, OrganizationInvitationService $invitations): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasRoleInOrganization($organization->id, [OrganizationMember::ROLE_ORGANIZATION_OWNER]),
            403
        );
        abort_if($invitation->organization_id !== $organization->id, 404);

        return response()->json([
            'message' => 'Đã thu hồi lời mời thành công.',
            'invitation' => $invitations->revoke($user, $invitation, $request->ip()),
        ]);
    }
}
