<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\Rules\TournamentAccess;
use App\Models\Tournament;
use App\Models\TournamentGrant;
use App\Models\User;

/**
 * UC-7.18, D21, D22: the owner grants a public tournament to staff assigned to its venue (official), or an internal
 * tournament to a representative account.
 */
class GrantTournamentRole
{
    public function __construct(private readonly TournamentRepositoryInterface $tournaments) {}

    public function execute(Tournament $tournament, User $owner, string $role, string $email): TournamentGrant
    {
        TournamentAccess::authorize($tournament, $owner, 'grant');
        $expected = $tournament->isPublic() ? TournamentGrant::ROLE_OFFICIAL : TournamentGrant::ROLE_REPRESENTATIVE;
        $user = $this->tournaments->findUserByEmail($email);
        if ($role !== $expected || $user === null) {
            TournamentAccess::fail('GRANT_INVALID', $role !== $expected
                ? ($tournament->isPublic() ? 'Giải công khai chỉ giao cho nhân viên vận hành.' : 'Giải nội bộ chỉ cấp quyền đại diện.')
                : 'Không tìm thấy tài khoản với email này.');
        }
        if ($role === TournamentGrant::ROLE_OFFICIAL && ($user->canManageVenue($tournament->venue) || ! $user->canOperateVenue($tournament->venue))) {
            TournamentAccess::fail('GRANT_INVALID', 'Chỉ giao cho nhân viên được phân công tại cơ sở của giải.');
        }

        return $this->tournaments->grant($tournament, $user, $role, $owner);
    }
}
