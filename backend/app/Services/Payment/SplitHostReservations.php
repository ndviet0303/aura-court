<?php

namespace App\Services\Payment;

use App\Models\Organization;
use App\Models\Reservation;
use App\Models\SplitGroup;
use App\Models\User;
use App\Services\Infrastructure\FeatureFlagService;
use App\Services\Infrastructure\TenantContext;
use Illuminate\Support\Facades\DB;

/** The split group of a booking, reached by the booking's own customer (the host). */
class SplitHostReservations
{
    public function __construct(
        private readonly SplitPaymentService $splits,
        private readonly FeatureFlagService $features,
    ) {}

    // The host is a customer, who usually does not belong to the venue's organization.
    public function find(User $host, string $identifier): Reservation
    {
        $reservation = Reservation::query()->withoutGlobalScope('tenant_isolation')
            ->where('user_id', $host->id)
            ->where(ctype_digit($identifier) ? 'id' : 'code', $identifier)
            ->firstOrFail();
        $organization = $reservation->organization;
        abort_unless($organization instanceof Organization, 404);
        app(TenantContext::class)->setOrganization($organization);
        $this->features->assertEnabled('marketplace_split_payments', $organization->id);

        return $reservation;
    }

    public function group(Reservation $reservation): ?SplitGroup
    {
        return SplitGroup::query()->where('reservation_id', $reservation->id)->with('shares', 'reservation')->first();
    }

    /** @param  array<int, array{amount: int}>  $shares */
    public function create(Reservation $reservation, User $host, array $shares): SplitGroup
    {
        return DB::transaction(function () use ($reservation, $host, $shares): SplitGroup {
            Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            // One group per booking: answer a second request plainly instead of with the unique-index error.
            abort_if(SplitGroup::query()->where('reservation_id', $reservation->id)->exists(), 409, 'This booking already has a split group.');

            return $this->splits->create($reservation, $host, $shares)->load('reservation');
        });
    }
}
