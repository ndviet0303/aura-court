<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Domain\Subscription\Actions\EnforcePlanQuota;
use App\Domain\Venue\DTOs\CreateDraftVenueData;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateDraftVenue
{
    public function __construct(
        private readonly VenueLifecycleRepositoryInterface $venueRepo,
        private readonly OrganizationLockRepositoryInterface $orgLocks,
        private readonly EnforcePlanQuota $quota,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    public function execute(CreateDraftVenueData $data): Venue
    {
        return DB::transaction(function () use ($data) {
            $org = $this->orgLocks->lockOrganizationWithMembersForUpdate($data->organizationId);
            if (! $org) {
                abort(404, 'Câu lạc bộ / tổ chức không tồn tại.');
            }

            // Verify permissions: owner or venue_manager
            $hasPermission = $org->members()
                ->where('user_id', $data->actorId)
                ->whereIn('role', ['organization_owner', 'venue_manager'])
                ->where('status', 'active')
                ->exists();

            if (! $hasPermission) {
                abort(403, 'Bạn không có quyền tạo cơ sở cho câu lạc bộ này.');
            }

            // Enforce plan quota before creation
            $this->quota->execute($data->organizationId, 'venues', 1);

            $baseSlug = Str::slug($data->name) ?: 'venue';
            $slug = $baseSlug.'-'.substr(uniqid(), -5);

            $venue = $this->venueRepo->createVenue([
                'organization_id' => $org->id,
                'name' => $data->name,
                'slug' => $slug,
                'address' => $data->address,
                'city' => $data->city,
                'district' => $data->district,
                'ward' => $data->ward,
                'phone' => $data->phone,
                'email' => $data->email,
                'description' => $data->description,
                'images' => $data->images,
                'google_maps_url' => $data->googleMapsUrl,
                'amenities' => $data->amenities,
                'status' => 'draft',
                'is_active' => false, // MUST start as non-public draft!
            ]);

            $this->notif->recordAudit(
                userId: $data->actorId,
                action: 'venue.draft.created',
                auditable: $venue,
                metadata: [
                    'organization_id' => $org->id,
                    'name' => $data->name,
                    'status' => 'draft',
                ]
            );

            return $venue;
        });
    }
}
