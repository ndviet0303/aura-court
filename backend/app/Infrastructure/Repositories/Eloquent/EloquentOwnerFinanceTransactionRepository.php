<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\OwnerFinanceTransactionRepositoryInterface;
use App\Domain\Finance\DTOs\OwnerFinanceFilterData;
use App\Models\OwnerPaymentReceipt;
use App\Models\Reservation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentOwnerFinanceTransactionRepository implements OwnerFinanceTransactionRepositoryInterface
{
    private const TENANT_SCOPE = 'tenant_isolation';

    /** Purposes paid for court bookings (recurring_period: past monthly invoices); POS orders and tournament fees are not. */
    private const BOOKING_PURPOSES = ['booking', 'recurring_period', 'split_share'];

    private const TRANSACTION_AT = 'coalesce(owner_payment_receipts.event_at, owner_payment_receipts.received_at)';

    public function paginate(OwnerFinanceFilterData $filter): LengthAwarePaginator
    {
        $receipts = OwnerPaymentReceipt::query()
            ->with(['venue', 'allocation', 'attempt'])
            ->where('organization_id', $filter->organizationId)
            ->when($filter->venueId, fn ($query, int $venueId) => $query->where('venue_id', $venueId))
            // An outgoing transfer is not money received.
            ->where('transfer_type', 'in')
            // A receipt with no purpose yet (no code, unknown code, refused early) is exactly what the owner reconciles.
            ->where(fn ($query) => $query->whereNull('purpose')->orWhereIn('purpose', self::BOOKING_PURPOSES))
            ->whereRaw(self::TRANSACTION_AT.' >= ?', [$filter->startsAt])
            ->whereRaw(self::TRANSACTION_AT.' < ?', [$filter->endsAt])
            ->orderByRaw(self::TRANSACTION_AT.' desc')
            ->orderByDesc('id')
            ->paginate($filter->perPage);

        $codes = $receipts->getCollection()->map(fn (OwnerPaymentReceipt $receipt) => $this->bookingCode($receipt))->filter()->unique()->values();
        $reservations = Reservation::query()->withoutGlobalScope(self::TENANT_SCOPE)
            ->where('organization_id', $filter->organizationId)
            ->whereIn('code', $codes)
            ->with(['court.venue', 'slots' => fn ($query) => $query->withoutGlobalScope(self::TENANT_SCOPE)])
            ->get()
            ->keyBy('code');

        $receipts->getCollection()->each(fn (OwnerPaymentReceipt $receipt) => $receipt->setRelation('reservation', $reservations->get($this->bookingCode($receipt) ?? '')));

        return $receipts;
    }

    /** D12: the booking named by the allocation, else by the attempt; only a booking request's id is a booking code, never an amount. */
    private function bookingCode(OwnerPaymentReceipt $receipt): ?string
    {
        $source = $receipt->allocation ?? $receipt->attempt;

        return $source?->getAttribute('purpose') === 'booking' ? $source->getAttribute('purpose_id') : null;
    }
}
