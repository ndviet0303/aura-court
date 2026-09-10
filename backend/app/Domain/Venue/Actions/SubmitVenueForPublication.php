<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Models\VenuePublicationRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitVenueForPublication
{
    public function __construct(
        private readonly VenueLifecycleRepositoryInterface $venueRepo,
        private readonly OrganizationLockRepositoryInterface $orgLocks,
        private readonly EvaluateVenueReadiness $readiness,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    public function execute(int $venueId, int $actorId): VenuePublicationRevision
    {
        return DB::transaction(function () use ($venueId, $actorId) {
            $venue = $this->venueRepo->findByIdWithLock($venueId);
            if (! $venue) {
                abort(404, 'Cơ sở thể thao không tồn tại.');
            }

            $org = $this->orgLocks->lockOrganizationWithMembersForUpdate($venue->organization_id);
            if (! $org) {
                abort(404, 'Câu lạc bộ không tồn tại.');
            }

            $hasPermission = $org->members()
                ->where('user_id', $actorId)
                ->whereIn('role', ['organization_owner', 'venue_manager'])
                ->where('status', 'active')
                ->exists();

            if (! $hasPermission) {
                abort(403, 'Bạn không có quyền gửi duyệt xuất bản cho cơ sở này.');
            }

            $hasPendingRevision = $venue->publicationRevisions()
                ->where('status', 'pending')
                ->exists();

            if ($hasPendingRevision || $venue->isPendingPublication()) {
                abort(409, 'Cơ sở hiện đang có bản sửa đổi đang trong hàng đợi chờ xét duyệt xuất bản.');
            }

            // Real-time readiness check
            $eval = $this->readiness->execute($venue);
            if (! $eval['is_ready']) {
                throw ValidationException::withMessages([
                    'readiness' => 'Cơ sở chưa đáp ứng đầy đủ điều kiện để gửi duyệt xuất bản.',
                    'missing_items' => $eval['missing_items'],
                ]);
            }

            // Build configuration snapshot
            $venue->loadMissing(['courts.priceSlots']);
            $snapshot = [
                'venue' => [
                    'id' => $venue->id,
                    'name' => (string) $venue->name,
                    'address' => $venue->address,
                    'amenities' => $venue->amenities,
                    'google_maps_url' => $venue->google_maps_url,
                ],
                'courts' => $venue->courts->where('is_active', true)->map(fn ($court) => [
                    'id' => $court->id,
                    'name' => (string) $court->name,
                    'slug' => $court->slug,
                    'price_slots' => $court->priceSlots->where('is_active', true)->map(fn ($slot) => [
                        'weekday' => $slot->weekday,
                        'starts_at' => $slot->starts_at,
                        'ends_at' => $slot->ends_at,
                        'price' => (int) $slot->price,
                    ])->values()->all(),
                ])->values()->all(),
                'organization' => [
                    'id' => $venue->organization_id,
                    'name' => $org->name,
                    'cancellation_grace_hours' => $org->cancellation_grace_hours ?? 12,
                ],
                'submitted_at' => now()->toIso8601String(),
            ];

            $maxRev = (int) ($venue->publicationRevisions()->max('revision_number') ?? 0);
            $nextRev = $maxRev + 1;

            $revision = $this->venueRepo->createPublicationRevision($venue, [
                'revision_number' => $nextRev,
                'status' => 'pending',
                'configuration_snapshot' => $snapshot,
                'submitted_by' => $actorId,
                'submitted_at' => now(),
            ]);

            if (! $venue->isPublished()) {
                $this->venueRepo->updateStatus($venue, 'pending_publication');
            }
            $venue->update(['current_publication_revision_id' => $revision->id]);

            $this->notif->recordAudit(
                userId: $actorId,
                action: 'venue.publication.submitted',
                auditable: $revision,
                metadata: [
                    'venue_id' => $venue->id,
                    'revision_number' => $nextRev,
                ]
            );

            return $revision;
        });
    }
}
