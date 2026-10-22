<?php

namespace App\Services\Organization;

use App\Models\ElevatedAuditLog;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Notifications\AuraBookingNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Staff invitations of module 1: BR-17 (who may be invited), BR-18 (notice, 7-day validity, revoke), BR-19 (invitee answers). */
class OrganizationInvitationService
{
    /** @param  array{email: string, venue_id?: ?int, venue_ids?: list<int>}  $data */
    public function invite(Organization $organization, User $inviter, array $data, ?string $ip): OrganizationInvitation
    {
        $email = Str::lower(trim($data['email']));
        $invitee = User::whereRaw('lower(email) = ?', [$email])->first();
        $this->assertInvitable($organization, $inviter, $invitee);

        // BR-17/23: an invitation only ever grants staff; ownership comes from partner approval.
        $permissions = StaffMembershipService::venuePermissions($data['venue_ids'] ?? [(int) $data['venue_id']]);
        $invitation = OrganizationInvitation::create([
            'organization_id' => $organization->id,
            'invited_by' => $inviter->id,
            'email' => $invitee->email,
            'role' => OrganizationMember::ROLE_STAFF,
            'venue_id' => $permissions['venue_id'],
            'venue_ids' => $permissions['venue_ids'],
            'permissions' => $permissions,
            'token' => Str::random(64),
            'status' => OrganizationInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $invitee->notify(new AuraBookingNotification(
            'staff_invitation',
            'Lời mời tham gia quản trị',
            "{$inviter->name} mời bạn tham gia tổ chức {$organization->name} với vai trò Nhân viên. Lời mời có hiệu lực đến ".$invitation->expires_at->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y').'.',
            '/invitations'
        ));

        $this->audit($inviter, $organization->id, 'STAFF_INVITATION_CREATED', "Mời {$invitee->email} tham gia tổ chức với vai trò nhân viên", $ip, [
            'invitation_id' => $invitation->id,
            'email' => $invitee->email,
            'role' => OrganizationMember::ROLE_STAFF,
            'venue_ids' => $permissions['venue_ids'],
        ]);

        return $invitation;
    }

    /** @return Collection<int, OrganizationInvitation> */
    public function pendingFor(User $user): Collection
    {
        return OrganizationInvitation::query()
            ->whereRaw('lower(email) = ?', [Str::lower($user->email)])
            ->where('status', OrganizationInvitation::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->with(['organization:id,name', 'inviter:id,name', 'venue:id,name'])
            ->latest()
            ->get();
    }

    /** @return array{invitation: OrganizationInvitation, member: ?OrganizationMember, already: bool} */
    public function accept(User $user, OrganizationInvitation $invitation, ?string $ip): array
    {
        $this->assertRecipient($user, $invitation);
        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['email' => 'Vui lòng xác thực email trước khi chấp nhận lời mời.']);
        }
        $this->expireIfDue($invitation);

        return DB::transaction(function () use ($user, $invitation, $ip) {
            $invitation = OrganizationInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            if ($invitation->isAccepted()) {
                $member = OrganizationMember::where('organization_id', $invitation->organization_id)->where('user_id', $user->id)->first();

                return ['invitation' => $invitation, 'member' => $member, 'already' => true];
            }
            $this->assertAnswerable($invitation);
            abort_if($this->holdsOperatorRole($invitation->organization_id, $user->id), 422, 'Bạn đã là chủ hoặc nhân viên của tổ chức này.');

            $invitation->update(['status' => OrganizationInvitation::STATUS_ACCEPTED, 'accepted_at' => now()]);
            $member = OrganizationMember::updateOrCreate(
                ['organization_id' => $invitation->organization_id, 'user_id' => $user->id],
                ['role' => OrganizationMember::ROLE_STAFF, 'status' => 'active', 'permissions' => $invitation->permissions]
            );

            $this->audit($user, $invitation->organization_id, 'STAFF_INVITATION_ACCEPTED', "Chấp nhận lời mời tham gia với vai trò {$invitation->role}", $ip, [
                'invitation_id' => $invitation->id,
                'role' => $invitation->role,
            ]);
            $invitation->inviter?->notify(new AuraBookingNotification(
                'staff_invitation_accepted',
                'Lời mời được chấp nhận',
                "{$user->name} ({$user->email}) đã chấp nhận lời mời tham gia tổ chức.",
                '/admin/staff'
            ));

            return ['invitation' => $invitation, 'member' => $member->fresh(), 'already' => false];
        });
    }

    public function decline(User $user, OrganizationInvitation $invitation, ?string $ip): OrganizationInvitation
    {
        $this->assertRecipient($user, $invitation);
        $this->expireIfDue($invitation);

        return DB::transaction(function () use ($user, $invitation, $ip) {
            $invitation = OrganizationInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            $this->assertAnswerable($invitation);

            $invitation->update(['status' => OrganizationInvitation::STATUS_DECLINED, 'declined_at' => now()]);
            $this->audit($user, $invitation->organization_id, 'STAFF_INVITATION_DECLINED', "Từ chối lời mời tham gia với vai trò {$invitation->role}", $ip, [
                'invitation_id' => $invitation->id,
                'role' => $invitation->role,
            ]);
            $invitation->inviter?->notify(new AuraBookingNotification(
                'staff_invitation_declined',
                'Lời mời bị từ chối',
                "{$user->name} ({$user->email}) đã từ chối lời mời tham gia tổ chức.",
                '/admin/staff'
            ));

            return $invitation;
        });
    }

    public function revoke(User $actor, OrganizationInvitation $invitation, ?string $ip): OrganizationInvitation
    {
        return DB::transaction(function () use ($actor, $invitation, $ip) {
            $invitation = OrganizationInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            if (! $invitation->isPending()) {
                abort(422, 'Chỉ có thể thu hồi lời mời đang chờ phản hồi.');
            }

            $invitation->update(['status' => OrganizationInvitation::STATUS_REVOKED, 'revoked_at' => now()]);
            // Membership rows parked as 'invited' by the earlier flow never become active.
            OrganizationMember::where('organization_id', $invitation->organization_id)
                ->where('status', 'invited')
                ->whereHas('user', fn ($q) => $q->whereRaw('lower(email) = ?', [Str::lower($invitation->email)]))
                ->delete();

            $this->audit($actor, $invitation->organization_id, 'STAFF_INVITATION_REVOKED', "Thu hồi lời mời cho {$invitation->email}", $ip, [
                'invitation_id' => $invitation->id,
                'email' => $invitation->email,
            ]);

            return $invitation;
        });
    }

    /** @phpstan-assert User $invitee */
    private function assertInvitable(Organization $organization, User $inviter, ?User $invitee): void
    {
        $reason = match (true) {
            ! $invitee => 'Không tìm thấy tài khoản với email này. Người được mời cần đăng ký tài khoản trước.',
            $invitee->id === $inviter->id => 'Bạn không thể tự mời chính mình.',
            $invitee->isRestricted() => 'Tài khoản được mời đang bị khóa hoặc hạn chế.',
            ! $invitee->hasVerifiedEmail() => 'Tài khoản được mời chưa xác thực email.',
            // A customer membership does not block an invitation (BR-17); acceptance turns it into staff.
            $this->holdsOperatorRole($organization->id, $invitee->id) => 'Người dùng đã là chủ hoặc nhân viên của tổ chức.',
            $organization->invitations()->whereRaw('lower(email) = ?', [Str::lower($invitee->email)])
                ->where('status', OrganizationInvitation::STATUS_PENDING)->where('expires_at', '>', now())->exists() => 'Người dùng đang có một lời mời chưa phản hồi từ tổ chức này.',
            default => null,
        };
        if ($reason !== null) {
            throw ValidationException::withMessages(['email' => $reason]);
        }
    }

    /** An owner row in any status, or an active staff row; a suspended staff member may be invited back. */
    private function holdsOperatorRole(int $organizationId, int $userId): bool
    {
        return OrganizationMember::where('organization_id', $organizationId)->where('user_id', $userId)
            ->where(fn ($q) => $q->where('role', OrganizationMember::ROLE_ORGANIZATION_OWNER)
                ->orWhere(fn ($staff) => $staff->where('role', OrganizationMember::ROLE_STAFF)->where('status', 'active')))
            ->exists();
    }

    private function assertRecipient(User $user, OrganizationInvitation $invitation): void
    {
        abort_unless(Str::lower($user->email) === Str::lower($invitation->email), 403, 'Bạn không phải là người nhận của lời mời này.');
    }

    /** Records the expiry outside the answering transaction, which an abort rolls back. */
    private function expireIfDue(OrganizationInvitation $invitation): void
    {
        if ($invitation->status === OrganizationInvitation::STATUS_PENDING && $invitation->isExpired()) {
            $invitation->update(['status' => OrganizationInvitation::STATUS_EXPIRED]);
        }
    }

    private function assertAnswerable(OrganizationInvitation $invitation): void
    {
        abort_unless($invitation->isPending(), 422, match ($invitation->status) {
            OrganizationInvitation::STATUS_EXPIRED, OrganizationInvitation::STATUS_PENDING => 'Lời mời đã hết hạn.',
            OrganizationInvitation::STATUS_REVOKED => 'Lời mời đã bị thu hồi.',
            OrganizationInvitation::STATUS_DECLINED => 'Lời mời đã bị từ chối.',
            default => 'Lời mời đã được phản hồi.',
        });
    }

    private function audit(User $actor, int $organizationId, string $action, string $purpose, ?string $ip, array $metadata): void
    {
        ElevatedAuditLog::create([
            'user_id' => $actor->id,
            'organization_id' => $organizationId,
            'action' => $action,
            'purpose' => $purpose,
            'outcome' => 'success',
            'ip_address' => $ip,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
