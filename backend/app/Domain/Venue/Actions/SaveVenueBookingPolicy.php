<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Models\OrganizationMember;
use App\Models\VenueBookingPolicy;
use Illuminate\Support\Facades\DB;

class SaveVenueBookingPolicy
{
    public function __construct(
        private readonly VenueLifecycleRepositoryInterface $venueRepo,
        private readonly OrganizationLockRepositoryInterface $orgLocks,
        private readonly UserRepositoryInterface $users,
    ) {}

    public function execute(int $venueId, int $actorId, array $settings): VenueBookingPolicy
    {
        return DB::transaction(function () use ($venueId, $actorId, $settings) {
            $venue = $this->venueRepo->findByIdWithLock($venueId);
            if (! $venue) {
                abort(404, 'Cơ sở thể thao không tồn tại.');
            }

            $org = $this->orgLocks->lockOrganizationWithMembersForUpdate($venue->organization_id);
            if (! $org) {
                abort(404, 'Câu lạc bộ không tồn tại.');
            }

            $actor = $this->users->findById($actorId);
            $canManage = $actor && (
                $actor->isPlatformAdmin()
                || ($actor->canManageVenue($venue) && $actor->hasRoleInOrganization($org->id, [
                    OrganizationMember::ROLE_ORGANIZATION_OWNER,
                    OrganizationMember::ROLE_VENUE_MANAGER,
                ]))
            );
            abort_unless($canManage, 403, 'Bạn không có quyền thay đổi chính sách đặt sân này.');

            $version = (int) VenueBookingPolicy::query()
                ->where('venue_id', $venue->id)
                ->max('version') + 1;

            return VenueBookingPolicy::create([
                'venue_id' => $venue->id,
                'version' => $version,
                'settings' => $settings,
                'created_by' => $actorId,
                'effective_at' => now(),
            ]);
        });
    }
}
