<?php

namespace App\Services\Organization;

use App\Models\ElevatedAuditLog;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** BR-20/22/23: the owner sets a staff member's venues and status or ends the membership, each change audited with before/after. */
class StaffMembershipService
{
    /** @param  array{status?: string, venue_ids?: list<int>}  $data */
    public function update(Organization $organization, User $actor, OrganizationMember $member, array $data, ?string $ip): OrganizationMember
    {
        $this->assertStaffOf($organization, $member);

        return DB::transaction(function () use ($organization, $actor, $member, $data, $ip) {
            $member = OrganizationMember::query()->lockForUpdate()->findOrFail($member->id);
            $before = $this->snapshot($member);

            $status = $data['status'] ?? $member->status;
            $payload = ['status' => $status];
            if (array_key_exists('venue_ids', $data)) {
                // An empty list leaves the staff member without any venue, so without venue access (BR-20).
                $payload['permissions'] = self::venuePermissions($data['venue_ids']);
            }
            $member->update($payload);

            $this->audit($actor, $organization, $member, 'STAFF_ROLE_UPDATED', 'Cập nhật phạm vi nhân viên', $ip, $before, $this->snapshot($member));

            return $member;
        });
    }

    /** Ending staff status keeps the account, its customer membership and its history (BR-22). */
    public function terminate(Organization $organization, User $actor, OrganizationMember $member, ?string $ip): void
    {
        $this->assertStaffOf($organization, $member);

        DB::transaction(function () use ($organization, $actor, $member, $ip) {
            $member = OrganizationMember::query()->lockForUpdate()->findOrFail($member->id);
            $before = $this->snapshot($member);
            $member->update(['role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active', 'permissions' => null]);

            $this->audit($actor, $organization, $member, 'STAFF_MEMBER_REMOVED', 'Chấm dứt tư cách nhân viên', $ip, $before, $this->snapshot($member));
        });
    }

    /**
     * @param  list<int>  $venueIds
     * @return array{venue_ids: list<int>, venue_id: ?int}
     */
    public static function venuePermissions(array $venueIds): array
    {
        $venueIds = array_values(array_unique(array_map('intval', $venueIds)));

        return ['venue_ids' => $venueIds, 'venue_id' => $venueIds[0] ?? null];
    }

    private function assertStaffOf(Organization $organization, OrganizationMember $member): void
    {
        abort_if($member->organization_id !== $organization->id, 404);
        abort_unless($member->role === OrganizationMember::ROLE_STAFF, 422, 'Chỉ điều chỉnh được tư cách nhân viên.');
    }

    /** @return array{role: string, status: string, venue_ids: list<int>} */
    private function snapshot(OrganizationMember $member): array
    {
        return ['role' => $member->role, 'status' => $member->status, 'venue_ids' => $member->assignedVenueIds()];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function audit(User $actor, Organization $organization, OrganizationMember $member, string $action, string $purpose, ?string $ip, array $before, array $after): void
    {
        $member->loadMissing('user:id,email');
        ElevatedAuditLog::create([
            'user_id' => $actor->id,
            'organization_id' => $organization->id,
            'action' => $action,
            'purpose' => "{$purpose} {$member->user?->email}",
            'outcome' => 'success',
            'ip_address' => $ip,
            'metadata' => [
                'member_id' => $member->id,
                'target_user_id' => $member->user_id,
                'target_email' => $member->user?->email,
                'before' => $before,
                'after' => $after,
            ],
            'created_at' => now(),
        ]);
    }
}
