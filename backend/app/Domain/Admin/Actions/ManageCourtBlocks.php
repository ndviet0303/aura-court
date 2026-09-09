<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\CourtBlockRepositoryInterface;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\CourtBlock;
use App\Models\ReservationSlot;
use Carbon\Carbon;

class ManageCourtBlocks
{
    public function __construct(
        private readonly CourtBlockRepositoryInterface $courtBlocks,
    ) {}

    public function createBlock(Court $court, array $data): CourtBlock
    {
        $startsAt = Carbon::parse($data['starts_at'])->utc();
        $endsAt = Carbon::parse($data['ends_at'])->utc();

        // Check conflicting confirmed V1 booking slots
        $v1Slots = BookingSlot::query()
            ->where('court_id', $court->id)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->whereHas('booking', fn ($b) => $b->whereIn('status', ['confirmed', 'pending_payment']))
            ->with('booking')
            ->get();

        // Check conflicting confirmed V2 reservation slots
        $v2Slots = ReservationSlot::query()
            ->where('court_id', $court->id)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->whereHas('reservation', fn ($r) => $r->whereIn('status', ['confirmed', 'held']))
            ->with('reservation')
            ->get();

        if ($v1Slots->isNotEmpty() || $v2Slots->isNotEmpty()) {
            $conflicts = [];
            foreach ($v1Slots as $s) {
                $conflicts[] = $s->booking?->code ?: "BK-{$s->booking_id}";
            }
            foreach ($v2Slots as $s) {
                $conflicts[] = $s->reservation?->code ?: "RES-{$s->reservation_id}";
            }
            $uniqueConflicts = array_values(array_unique($conflicts));

            abort(response()->json([
                'message' => 'Khung giờ đóng sân bị trùng với các lịch đặt đã xác nhận: ' . implode(', ', $uniqueConflicts),
                'conflicting_bookings' => $uniqueConflicts,
            ], 409));
        }

        return $this->courtBlocks->createForCourt($court, [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => $data['reason'] ?? null,
        ]);
    }

    public function deleteBlock(Court $court, CourtBlock $block): void
    {
        abort_unless($block->court_id === $court->id, 404);
        $this->courtBlocks->delete($block);
    }
}
