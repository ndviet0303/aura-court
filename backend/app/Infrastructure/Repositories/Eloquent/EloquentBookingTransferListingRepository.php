<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\BookingTransferListingRepositoryInterface;
use App\Models\BookingTransferListing;
use App\Models\BookingTransferListingItem;
use App\Models\ConversationMessage;
use App\Models\Reservation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** The market is shared by every organization (D10), so bookings are read without the tenant scope. */
class EloquentBookingTransferListingRepository implements BookingTransferListingRepositoryInterface
{
    private const TENANT_SCOPE = 'tenant_isolation';

    /** A booking starts at its first slot (Reservation::starts_at); slot times are stored in UTC. */
    private const STARTS_AT = '(select min(rs.starts_at) from reservation_slots rs where rs.reservation_id = reservations.id)';

    public function candidates(int $userId): Collection
    {
        return Reservation::query()->withoutGlobalScope(self::TENANT_SCOPE)
            ->where('user_id', $userId)->where('status', 'confirmed')
            ->whereRaw(self::STARTS_AT.' > ?', [now()->utc()->format('Y-m-d H:i:s')])
            ->orderByRaw(self::STARTS_AT)
            ->with(['slots' => fn ($query) => $query->withoutGlobalScope(self::TENANT_SCOPE), 'court.venue'])
            ->get();
    }

    public function find(int $id): ?BookingTransferListing
    {
        return BookingTransferListing::query()->find($id);
    }

    public function create(int $sellerId, ?string $note, array $items): BookingTransferListing
    {
        $listing = BookingTransferListing::create(['seller_id' => $sellerId, 'note' => $note]);
        $listing->items()->createMany(array_map(fn (array $item): array => $item + ['status' => BookingTransferListingItem::STATUS_LISTED], $items));

        return $listing;
    }

    public function search(?string $search, int $perPage): LengthAwarePaginator
    {
        // Strictly more than two hours before the start (BR-177).
        $cutoff = now()->utc()->addHours(2)->format('Y-m-d H:i:s');
        $term = '%'.addcslashes(trim((string) $search), '%_\\').'%';

        // UC-9.3: a listing stays on the market while a booking in it is still offered, open or held for a pending
        // transaction (a hold can be cancelled); one whose bookings are all transferred, withdrawn or past the cutoff
        // offers nothing more (BR-190) and leaves it.
        return BookingTransferListing::query()
            ->whereHas('items', fn (Builder $item) => $item->where('status', BookingTransferListingItem::STATUS_LISTED)
                ->whereHas('reservation', fn (Builder $reservation) => $reservation->where('status', 'confirmed')->whereRaw(self::STARTS_AT.' > ?', [$cutoff])))
            ->when(trim((string) $search) !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('note', 'ilike', $term)
                ->orWhereHas('items.reservation.court', fn (Builder $court) => $court->where('code', 'ilike', $term)
                    ->orWhereHas('venue', fn (Builder $venue) => $venue->where('name', 'ilike', $term)->orWhere('address', 'ilike', $term)))))
            ->with($this->details())
            ->latest('id')
            ->paginate($perPage);
    }

    public function withDetails(BookingTransferListing $listing): BookingTransferListing
    {
        return $listing->load($this->details());
    }

    public function lockListedItems(BookingTransferListing $listing): Collection
    {
        return BookingTransferListingItem::query()->where('listing_id', $listing->id)
            ->where('status', BookingTransferListingItem::STATUS_LISTED)
            ->withExists('pendingTransferItems as held')
            ->orderBy('id')->lockForUpdate()->get();
    }

    public function withdraw(array $itemIds): void
    {
        BookingTransferListingItem::query()->whereKey($itemIds)->update(['status' => BookingTransferListingItem::STATUS_WITHDRAWN]);
    }

    public function markTransferred(array $reservationIds): void
    {
        BookingTransferListingItem::query()->whereIn('reservation_id', $reservationIds)
            ->where('status', BookingTransferListingItem::STATUS_LISTED)
            ->update(['status' => BookingTransferListingItem::STATUS_TRANSFERRED]);
    }

    public function contacts(BookingTransferListing $listing): array
    {
        return ConversationMessage::query()
            // The json path reads as text, so the id is compared as text.
            ->where('transfer->listing_id', (string) $listing->id)
            ->where('sender_user_id', '<>', $listing->seller_id)
            ->with('sender:id,name,avatar_url')
            ->orderByDesc('id')
            ->get()
            ->groupBy('sender_user_id')
            ->map(fn (Collection $messages): array => [
                'user' => ['id' => (int) $messages->first()->sender->id, 'name' => (string) $messages->first()->sender->name, 'avatar_url' => $messages->first()->sender->avatar_url],
                'conversation_id' => (int) $messages->first()->conversation_id,
                'reservation_ids' => $messages->flatMap(fn (ConversationMessage $message) => array_column((array) ($message->transfer['items'] ?? []), 'reservation_id'))
                    ->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all(),
                'contacted_at' => $messages->first()->created_at?->toIso8601String(),
            ])
            ->values()->all();
    }

    /** @return array<int|string, mixed> */
    private function details(): array
    {
        return [
            'seller:id,name,avatar_url',
            'items' => fn ($query) => $query->withExists('pendingTransferItems as held'),
            'items.reservation.slots' => fn ($query) => $query->withoutGlobalScope(self::TENANT_SCOPE),
            'items.reservation.court.venue',
        ];
    }
}
