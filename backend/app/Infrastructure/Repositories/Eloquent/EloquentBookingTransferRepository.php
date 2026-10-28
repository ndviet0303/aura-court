<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\BookingTransferRepositoryInterface;
use App\Models\BookingAttendance;
use App\Models\BookingTransfer;
use App\Models\BookingTransferItem;
use App\Models\BookingTransferListingItem;
use App\Models\OrganizationMember;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Transfers cross organizations, so every booking read here ignores the tenant scope. */
class EloquentBookingTransferRepository implements BookingTransferRepositoryInterface
{
    private const TENANT_SCOPE = 'tenant_isolation';

    public function reservations(array $ids, bool $lock = false): Collection
    {
        return Reservation::query()->withoutGlobalScope(self::TENANT_SCOPE)->whereKey($ids)->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->with(['slots' => fn ($query) => $query->withoutGlobalScope(self::TENANT_SCOPE), 'court.venue'])
            ->get()->keyBy('id');
    }

    public function facts(Reservation $reservation, ?int $exceptTransferId = null): array
    {
        $id = (int) $reservation->id;
        $items = fn (string $status) => BookingTransferItem::query()->where('reservation_id', $id)->where('status', $status);

        return [
            'used' => BookingAttendance::query()->withoutGlobalScope(self::TENANT_SCOPE)->where('reservation_id', $id)->exists(),
            'transferred' => $items(BookingTransferItem::STATUS_COMPLETED)->exists(),
            'incident' => $reservation->affectedCases()->open()->exists(),
            'held' => $items(BookingTransferItem::STATUS_PENDING)
                ->when($exceptTransferId !== null, fn ($query) => $query->where('booking_transfer_id', '<>', $exceptTransferId))->exists(),
            'listed' => BookingTransferListingItem::query()->where('reservation_id', $id)->where('status', BookingTransferListingItem::STATUS_LISTED)->exists(),
        ];
    }

    public function create(array $attributes, array $items): BookingTransfer
    {
        $transfer = BookingTransfer::create($attributes + ['status' => BookingTransfer::STATUS_PENDING]);
        $transfer->items()->createMany(array_map(fn (array $item): array => $item + ['status' => BookingTransferItem::STATUS_PENDING], $items));

        return $transfer;
    }

    public function lock(BookingTransfer $transfer): BookingTransfer
    {
        return BookingTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
    }

    public function close(BookingTransfer $transfer, string $status, array $attributes): BookingTransfer
    {
        $transfer->forceFill(['status' => $status] + $attributes)->save();
        $transfer->items()->update(['status' => $status]);

        return $transfer;
    }

    public function moveHolding(Reservation $reservation, int $buyerId, array $holder): void
    {
        ReservationEntitlement::query()->withoutGlobalScope(self::TENANT_SCOPE)
            ->where('reservation_id', $reservation->id)->where('status', 'active')
            ->update(['user_id' => $buyerId]);
        $reservation->forceFill([
            'user_id' => $buyerId,
            'metadata' => ['holder' => $holder] + (array) $reservation->metadata,
            'check_in_code' => $this->newCheckInCode(),
        ])->save();
    }

    public function forUser(int $userId): Collection
    {
        return BookingTransfer::query()
            ->where(fn ($query) => $query->where('seller_id', $userId)->orWhere('buyer_id', $userId))
            ->with($this->details())
            ->latest('id')
            ->get();
    }

    public function withDetails(BookingTransfer $transfer): BookingTransfer
    {
        return $transfer->load($this->details());
    }

    public function ownerIds(int $organizationId): array
    {
        return OrganizationMember::query()->where('organization_id', $organizationId)
            ->where('role', OrganizationMember::ROLE_ORGANIZATION_OWNER)->where('status', 'active')
            ->pluck('user_id')->map(fn ($id): int => (int) $id)->all();
    }

    /** @return array<int|string, mixed> */
    private function details(): array
    {
        return ['seller', 'buyer', 'items.reservation.slots' => fn ($query) => $query->withoutGlobalScope(self::TENANT_SCOPE), 'items.reservation.court.venue'];
    }

    /** Same shape as a booking code, with its own prefix, unique against both columns. */
    private function newCheckInCode(): string
    {
        do {
            $code = 'CHK'.strtoupper(Str::random(13));
        } while (Reservation::query()->withoutGlobalScope(self::TENANT_SCOPE)
            ->where(fn ($query) => $query->where('check_in_code', $code)->orWhere('code', $code))->exists());

        return $code;
    }
}
