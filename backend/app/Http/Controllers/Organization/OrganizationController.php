<?php

namespace App\Http\Controllers\Organization;

use App\Domain\Subscription\Actions\EnforcePlanQuota;
use App\Http\Controllers\Controller;
use App\Models\ElevatedAuditLog;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Services\Infrastructure\FeatureFlagService;
use App\Services\Infrastructure\TenantContext;
use App\Services\Organization\OrganizationInvitationService;
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
            $user->isPlatformAdmin() || $user->hasRoleInOrganization($organization->id, [OrganizationMember::ROLE_ORGANIZATION_OWNER]),
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

        $organization->update($updateData);

        return response()->json(['organization' => $organization->fresh(['venues.courts', 'members.user'])]);
    }

    public function members(Request $request, Organization $organization): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isPlatformAdmin() || $user->hasRoleInOrganization($organization->id, [
                OrganizationMember::ROLE_ORGANIZATION_OWNER,
                OrganizationMember::ROLE_VENUE_MANAGER,
            ]),
            403
        );

        $members = $organization->members()->with('user')->get();

        return response()->json(['members' => $members]);
    }

    public function updateMemberRole(Request $request, Organization $organization, OrganizationMember $member): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isPlatformAdmin() || $user->hasRoleInOrganization($organization->id, [OrganizationMember::ROLE_ORGANIZATION_OWNER]),
            403
        );

        abort_if($member->organization_id !== $organization->id, 404);

        $data = $request->validate([
            'role' => 'required|string|in:'.implode(',', OrganizationMember::VALID_ROLES),
            'status' => 'sometimes|string|in:active,suspended',
            'venue_id' => 'nullable|integer',
            'venue_ids' => 'nullable|array',
            'venue_ids.*' => 'integer',
            'permissions' => 'nullable|array',
        ]);

        // Prevent removing or suspending the last active owner
        if ($member->role === OrganizationMember::ROLE_ORGANIZATION_OWNER) {
            $isDemoting = $data['role'] !== OrganizationMember::ROLE_ORGANIZATION_OWNER;
            $isSuspending = ($data['status'] ?? null) === 'suspended';
            if ($isDemoting || $isSuspending) {
                $otherActiveOwners = $organization->members()
                    ->where('role', OrganizationMember::ROLE_ORGANIZATION_OWNER)
                    ->where('status', 'active')
                    ->where('id', '!=', $member->id)
                    ->count();
                if ($otherActiveOwners < 1) {
                    abort(422, 'Cannot demote or suspend the last active organization owner.');
                }
            }
        }

        // Enforce staff quota if role or status changes to active staff
        $isStaffRole = in_array($data['role'], [OrganizationMember::ROLE_VENUE_MANAGER, OrganizationMember::ROLE_STAFF, OrganizationMember::ROLE_CASHIER], true);
        $wasActiveStaffRole = in_array($member->role, [OrganizationMember::ROLE_VENUE_MANAGER, OrganizationMember::ROLE_STAFF, OrganizationMember::ROLE_CASHIER], true) && $member->status === 'active';
        $willBeActiveStaff = $isStaffRole && (($data['status'] ?? $member->status) === 'active');

        if ($willBeActiveStaff && ! $wasActiveStaffRole && $organization->hasActiveSubscription()) {
            app(EnforcePlanQuota::class)->execute($organization->id, 'staff', 1);
        }

        $updatePayload = ['role' => $data['role']];
        if (isset($data['status'])) {
            $updatePayload['status'] = $data['status'];
        }
        if (array_key_exists('permissions', $data) || array_key_exists('venue_id', $data) || array_key_exists('venue_ids', $data)) {
            $permissions = $data['permissions'] ?? ($member->permissions ?? []);
            if (! empty($data['venue_ids'])) {
                $permissions['venue_ids'] = array_values(array_map('intval', (array) $data['venue_ids']));
                $permissions['venue_id'] = $permissions['venue_ids'][0] ?? null;
            } elseif (! empty($data['venue_id'])) {
                $permissions['venue_ids'] = [(int) $data['venue_id']];
                $permissions['venue_id'] = (int) $data['venue_id'];
            }
            $updatePayload['permissions'] = $permissions;
        }

        $before = $member->only(['role', 'status', 'permissions']);
        $member->update($updatePayload);

        ElevatedAuditLog::create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'action' => 'STAFF_ROLE_UPDATED',
            'purpose' => "Cập nhật vai trò thành viên {$member->user?->email} thành {$data['role']}",
            'outcome' => 'success',
            'ip_address' => $request->ip(),
            'metadata' => [
                'member_id' => $member->id,
                'before' => $before,
                'after' => $member->only(['role', 'status', 'permissions']),
            ],
            'created_at' => now(),
        ]);

        return response()->json(['member' => $member->fresh()->load('user')]);
    }

    public function removeMember(Request $request, Organization $organization, OrganizationMember $member): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isPlatformAdmin() || $user->hasRoleInOrganization($organization->id, [OrganizationMember::ROLE_ORGANIZATION_OWNER]),
            403
        );

        abort_if($member->organization_id !== $organization->id, 404);

        if ($member->role === OrganizationMember::ROLE_ORGANIZATION_OWNER) {
            $otherActiveOwners = $organization->members()
                ->where('role', OrganizationMember::ROLE_ORGANIZATION_OWNER)
                ->where('status', 'active')
                ->where('id', '!=', $member->id)
                ->count();
            if ($otherActiveOwners < 1) {
                abort(422, 'Cannot remove the last active organization owner.');
            }
        }

        $targetEmail = $member->user?->email;
        $member->delete();

        ElevatedAuditLog::create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'action' => 'STAFF_MEMBER_REMOVED',
            'purpose' => "Xóa thành viên {$targetEmail}",
            'outcome' => 'success',
            'ip_address' => $request->ip(),
            'metadata' => [
                'removed_email' => $targetEmail,
            ],
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'Member removed successfully.']);
    }

    public function invitations(Request $request, Organization $organization): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isPlatformAdmin() || $user->hasRoleInOrganization($organization->id, [
                OrganizationMember::ROLE_ORGANIZATION_OWNER,
                OrganizationMember::ROLE_VENUE_MANAGER,
            ]),
            403
        );

        $query = $organization->invitations()->with(['inviter:id,name,email', 'venue:id,name']);
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $invitations = $query->latest()->get();

        return response()->json(['invitations' => $invitations]);
    }

    public function invite(Request $request, Organization $organization, OrganizationInvitationService $invitations): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isPlatformAdmin() || $user->hasRoleInOrganization($organization->id, [OrganizationMember::ROLE_ORGANIZATION_OWNER]),
            403
        );

        $data = $request->validate([
            'email' => 'required|email|max:254',
            'role' => 'required|string|in:'.implode(',', OrganizationMember::VALID_ROLES),
            'venue_id' => 'nullable|integer',
            'venue_ids' => 'nullable|array',
            'venue_ids.*' => 'integer',
            'permissions' => 'nullable|array',
        ]);

        return response()->json(['invitation' => $invitations->invite($organization, $user, $data, $request->ip())], 201);
    }

    public function revokeInvitation(Request $request, Organization $organization, OrganizationInvitation $invitation, OrganizationInvitationService $invitations): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isPlatformAdmin() || $user->hasRoleInOrganization($organization->id, [OrganizationMember::ROLE_ORGANIZATION_OWNER]),
            403
        );
        abort_if($invitation->organization_id !== $organization->id, 404);

        return response()->json([
            'message' => 'Đã thu hồi lời mời thành công.',
            'invitation' => $invitations->revoke($user, $invitation, $request->ip()),
        ]);
    }
}
