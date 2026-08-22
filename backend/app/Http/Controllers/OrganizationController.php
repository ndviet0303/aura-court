<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext) {}

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
        ]);

        $organization->update($data);

        return response()->json(['organization' => $organization->fresh()]);
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
            'email' => 'required|email|exists:users,email',
            'role' => 'required|string|in:' . implode(',', OrganizationMember::VALID_ROLES),
        ]);

        $targetUser = User::where('email', $data['email'])->firstOrFail();

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
