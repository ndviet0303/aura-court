<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Infrastructure\FeatureFlagService;
use App\Services\Infrastructure\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'cancellation_grace_hours' => 'nullable|numeric|min:0|max:168',
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
            'cancellation_grace_hours',
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

    public function addMember(Request $request, Organization $organization): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isPlatformAdmin() || $user->hasRoleInOrganization($organization->id, [OrganizationMember::ROLE_ORGANIZATION_OWNER]),
            403
        );

        $data = $request->validate([
            'email' => 'required|email',
            'role' => 'required|string|in:' . implode(',', OrganizationMember::VALID_ROLES),
        ]);

        $targetUser = User::where('email', $data['email'])->first();
        if (!$targetUser) {
            $namePart = explode('@', $data['email'])[0];
            $targetUser = User::create([
                'name' => ucfirst($namePart),
                'email' => $data['email'],
                'password' => bcrypt(\Illuminate\Support\Str::random(24)),
                'role' => $data['role'] === OrganizationMember::ROLE_ORGANIZATION_OWNER ? 'admin' : 'staff',
                'password_set' => false,
            ]);
        }

        $member = OrganizationMember::updateOrCreate(
            ['organization_id' => $organization->id, 'user_id' => $targetUser->id],
            ['role' => $data['role'], 'status' => 'active']
        );

        return response()->json(['member' => $member->load('user')], 201);
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
            'role' => 'required|string|in:' . implode(',', OrganizationMember::VALID_ROLES),
            'status' => 'sometimes|string|in:active,suspended',
        ]);

        // Prevent removing the last owner
        if ($member->role === OrganizationMember::ROLE_ORGANIZATION_OWNER && $data['role'] !== OrganizationMember::ROLE_ORGANIZATION_OWNER) {
            $ownerCount = $organization->members()->where('role', OrganizationMember::ROLE_ORGANIZATION_OWNER)->count();
            if ($ownerCount <= 1) {
                abort(422, 'Cannot demote the last organization owner.');
            }
        }

        $member->update($data);

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
            $ownerCount = $organization->members()->where('role', OrganizationMember::ROLE_ORGANIZATION_OWNER)->count();
            if ($ownerCount <= 1) {
                abort(422, 'Cannot remove the last organization owner.');
            }
        }

        $member->delete();

        return response()->json(['message' => 'Member removed successfully.']);
    }
}
