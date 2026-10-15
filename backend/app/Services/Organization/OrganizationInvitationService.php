<?php

namespace App\Services\Organization;

use App\Domain\Subscription\Actions\EnforcePlanQuota;
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
    private const STAFF_ROLES = [OrganizationMember::ROLE_VENUE_MANAGER, OrganizationMember::ROLE_STAFF, OrganizationMember::ROLE_CASHIER];

    private const ROLE_LABELS = [
        OrganizationMember::ROLE_ORGANIZATION_OWNER => 'Chủ cơ sở',
        OrganizationMember::ROLE_VENUE_MANAGER => 'Quản lý cơ sở',
        OrganizationMember::ROLE_STAFF => 'Nhân viên',
        OrganizationMember::ROLE_CASHIER => 'Thu ngân',
        OrganizationMember::ROLE_CUSTOMER => 'Khách hàng',
    ];

    public function invite(Organization $organization, User $inviter, array $data, ?string $ip): OrganizationInvitation
    {
        $email = Str::lower(trim($data['email']));
        $invitee = User::whereRaw('lower(email) = ?', [$email])->first();
        $this->assertInvitable($organization, $inviter, $invitee);

        if (in_array($data['role'], self::STAFF_ROLES, true) && $organization->hasActiveSubscription()) {
            app(EnforcePlanQuota::class)->execute($organization->id, 'staff', 1);
        }

        $permissions = self::permissionsFrom($data);
        $invitation = OrganizationInvitation::create([
            'organization_id' => $organization->id,
            'invited_by' => $inviter->id,
            'email' => $invitee->email,
            'role' => $data['role'],
            'venue_id' => $permissions['venue_id'] ?? null,
            'venue_ids' => $permissions['venue_ids'] ?? null,
            'permissions' => $permissions ?: null,
            'token' => Str::random(64),
            'status' => OrganizationInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $invitee->notify(new AuraBookingNotification(
            'staff_invitation',
            'Lời mời tham gia quản trị',
            "{$inviter->name} mời bạn tham gia tổ chức {$organization->name} với vai trò ".self::roleLabel($data['role'])
                .'. Lời mời có hiệu lực đến '.$invitation->expires_at->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y').'.',
            '/invitations'
        ));

        $this->audit($inviter, $organization->id, 'STAFF_INVITATION_CREATED', "Mời {$invitee->email} tham gia tổ chức với vai trò {$data['role']}", $ip, [
            'invitation_id' => $invitation->id,
            'email' => $invitee->email,
            'role' => $data['role'],
            'venue_ids' => $permissions['venue_ids'] ?? null,
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

            if (in_array($invitation->role, self::STAFF_ROLES, true) && $invitation->organization->hasActiveSubscription()) {
                app(EnforcePlanQuota::class)->execute($invitation->organization_id, 'staff', 1);
            }

            $invitation->update(['status' => OrganizationInvitation::STATUS_ACCEPTED, 'accepted_at' => now()]);
            $member = OrganizationMember::updateOrCreate(
                ['organization_id' => $invitation->organization_id, 'user_id' => $user->id],
                ['role' => $invitation->role, 'status' => 'active', 'permissions' => $invitation->permissions]
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

    /** venue_ids / venue_id / permissions of a member or invitation request, in the stored shape. */
    public static function permissionsFrom(array $data, array $current = []): array
    {
        $permissions = $data['permissions'] ?? $current;
        if (! empty($data['venue_ids'])) {
            $permissions['venue_ids'] = array_values(array_map('intval', (array) $data['venue_ids']));
            $permissions['venue_id'] = $permissions['venue_ids'][0] ?? null;
        } elseif (! empty($data['venue_id'])) {
            $permissions['venue_ids'] = [(int) $data['venue_id']];
            $permissions['venue_id'] = (int) $data['venue_id'];
        }
        if (empty($permissions) && ($data['role'] ?? null) === OrganizationMember::ROLE_ORGANIZATION_OWNER) {
            $permissions = ['*'];
        }

        return $permissions;
    }

    private static function roleLabel(string $role): string
    {
        return self::ROLE_LABELS[$role] ?? $role;
    }

    /** @phpstan-assert User $invitee */
    private function assertInvitable(Organization $organization, User $inviter, ?User $invitee): void
    {
        $reason = match (true) {
            ! $invitee => 'Không tìm thấy tài khoản với email này. Người được mời cần đăng ký tài khoản trước.',
            $invitee->id === $inviter->id => 'Bạn không thể tự mời chính mình.',
            $invitee->isRestricted() => 'Tài khoản được mời đang bị khóa hoặc hạn chế.',
            ! $invitee->hasVerifiedEmail() => 'Tài khoản được mời chưa xác thực email.',
            $organization->members()->where('user_id', $invitee->id)->where('status', 'active')->exists() => 'Người dùng đã là thành viên chính thức của tổ chức.',
            $organization->invitations()->whereRaw('lower(email) = ?', [Str::lower($invitee->email)])
                ->where('status', OrganizationInvitation::STATUS_PENDING)->where('expires_at', '>', now())->exists() => 'Người dùng đang có một lời mời chưa phản hồi từ tổ chức này.',
            default => null,
        };
        if ($reason !== null) {
            throw ValidationException::withMessages(['email' => $reason]);
        }
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
