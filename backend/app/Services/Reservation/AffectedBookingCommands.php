<?php

namespace App\Services\Reservation;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Events\CourtStatusUpdated;
use App\Models\AffectedReservationCase;
use App\Models\Court;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueSuspension;
use App\Services\Booking\WaitlistService;
use App\Services\Infrastructure\TenantContext;
use App\Services\Promotion\VoucherUsageService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Design D8: move-court, change-time and cancel for a booking with an open affected-booking case, by the owner or
 * staff assigned to its venue, with the customer's agreement. No quote, payment, wallet, journal or refund row is
 * written. Locks follow the global order: courts by id, venues, venue_suspensions, the booking's pending payment
 * attempts, the booking, then its cases. Every open case closes with the command's outcome.
 */
class AffectedBookingCommands
{
    private const ACTIVE_SLOTS = ['held', 'confirmed', 'in_use'];

    public function __construct(
        private readonly HeldReservationRelease $release,
        private readonly SlotSelectionPolicy $policy,
        private readonly DurableNotificationServiceInterface $notifications,
        private readonly WaitlistService $waitlists,
        private readonly VoucherUsageService $vouchers,
    ) {}

    /** The booking by id or code in the organization; 403 unless the actor operates its venue. */
    public function find(User $actor, Organization $organization, string $identifier): Reservation
    {
        $reservation = Reservation::query()->withoutGlobalScope('tenant_isolation')
            ->where('organization_id', $organization->id)
            ->when(ctype_digit($identifier), fn ($q) => $q->whereKey((int) $identifier), fn ($q) => $q->where('code', $identifier))
            ->with('court')
            ->firstOrFail();
        $venueId = $reservation->court?->venue_id;
        abort_unless($venueId !== null && $actor->canOperateVenue((int) $venueId), 403, 'Bạn không có quyền xử lý đơn tại cơ sở này.');
        app(TenantContext::class)->setOrganization($organization);

        return $reservation;
    }

    /** Same times on another court of the organization and sport; a slot in play moves, an ended one stays. */
    public function move(User $actor, Reservation $reservation, int $courtId, array $contact): Reservation
    {
        $target = Court::query()->with('venue')->findOrFail($courtId);
        abort_unless((int) $target->venue?->organization_id === (int) $reservation->organization_id
            && $actor->canOperateVenue((int) $target->venue_id), 403, 'Bạn không có quyền chuyển đơn sang sân này.');
        abort_if((int) $target->id === (int) $reservation->court_id, 422, 'Sân đích phải khác sân hiện tại của đơn.');
        abort_if($target->sport_type !== $reservation->court?->sport_type, 422, 'Sân đích phải cùng môn với sân hiện tại của đơn.');

        return $this->run($actor, $reservation, [$target->id], 'moved', $contact, function (Reservation $locked, Collection $slots) use ($target): array {
            $this->assertNoHeldVoucher($locked);
            $this->assertTarget((int) $target->id, $this->intervals($slots), $locked, true);
            ReservationSlot::query()->whereKey($slots->pluck('id')->all())->update(['court_id' => $target->id]);
            ReservationEntitlement::query()->whereIn('reservation_slot_id', $slots->pluck('id')->all())->update(['court_id' => $target->id]);
            $locked->forceFill(['court_id' => $target->id])->save();

            return ['court_id' => (int) $target->id, 'slots' => $this->intervals($slots, iso: true)];
        });
    }

    /** The unended part to a new time on the same court, keeping its total duration, from now on. */
    public function changeTime(User $actor, Reservation $reservation, Carbon $startsAt, Carbon $endsAt, array $contact): Reservation
    {
        return $this->run($actor, $reservation, [], 'time_changed', $contact, function (Reservation $locked, Collection $slots) use ($startsAt, $endsAt): array {
            $this->assertNoHeldVoucher($locked);
            $minutes = (int) $slots->sum(fn (ReservationSlot $slot): int => (int) $slot->starts_at->diffInMinutes($slot->ends_at));
            abort_unless($startsAt->getTimestamp() + $minutes * 60 === $endsAt->getTimestamp(), 422, 'Khung giờ mới phải giữ nguyên tổng thời lượng của đơn.');
            $delta = $startsAt->getTimestamp() - $slots->first()->starts_at->getTimestamp();
            abort_if($delta === 0, 422, 'Khung giờ mới phải khác khung giờ hiện tại.');

            $shifted = $slots->map(fn (ReservationSlot $slot): array => [
                'start' => $slot->starts_at->copy()->addSeconds($delta),
                'end' => $slot->ends_at->copy()->addSeconds($delta),
            ])->all();
            $this->assertTarget((int) $locked->court_id, $shifted, $locked, false);
            // Move the slot nearest the new time first so no slot overlaps one of its own not yet moved.
            foreach ($delta > 0 ? $slots->reverse() : $slots as $slot) {
                $slot->forceFill(['starts_at' => $slot->starts_at->copy()->addSeconds($delta), 'ends_at' => $slot->ends_at->copy()->addSeconds($delta)])->save();
                ReservationEntitlement::query()->where('reservation_slot_id', $slot->id)->update([
                    'starts_at' => $slot->starts_at->copy()->utc(),
                    'ends_at' => $slot->ends_at->copy()->utc(),
                ]);
            }

            return ['court_id' => (int) $locked->court_id, 'slots' => $this->intervals($slots, iso: true)];
        });
    }

    /** Release the unended court-time once; a hold also ends its payment obligation through module 4's release. */
    public function cancel(User $actor, Reservation $reservation, array $contact): Reservation
    {
        abort_if(filled($contact['refund_note'] ?? null) && ! $actor->canManageVenue((int) $reservation->court?->venue_id), 403,
            'Chỉ chủ cơ sở được ghi chú hoàn tiền ngoài nền tảng.');

        return $this->run($actor, $reservation, [], 'cancelled', $contact, function (Reservation $locked, Collection $slots): array {
            if (! $this->release->releaseHeld($locked, 'cancelled', 'operator_affected_booking')) {
                $locked->forceFill([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'metadata' => [...(array) $locked->metadata, 'release_reason' => 'operator_affected_booking'],
                ])->save();
                ReservationSlot::query()->whereKey($slots->pluck('id')->all())->update(['status' => 'cancelled']);
                ReservationEntitlement::query()->whereIn('reservation_slot_id', $slots->pluck('id')->all())->update(['status' => 'cancelled']);
            }

            return ['status' => 'cancelled'];
        });
    }

    /**
     * @param  list<int>  $targetCourtIds
     * @param  callable(Reservation, Collection<int, ReservationSlot>): array  $apply
     */
    private function run(User $actor, Reservation $reservation, array $targetCourtIds, string $outcome, array $contact, callable $apply): Reservation
    {
        // Read without locks to learn which courts to lock; everything is compared again under the locks.
        $before = $this->fingerprint($reservation);
        $courtIds = collect([(int) $reservation->court_id, ...$this->unended($reservation)->pluck('court_id')->map(fn ($id): int => (int) $id), ...$targetCourtIds])
            ->unique()->sort()->values()->all();

        try {
            return DB::transaction(function () use ($actor, $reservation, $courtIds, $outcome, $contact, $apply, $before): Reservation {
                $courts = Court::query()->whereIn('id', $courtIds)->orderBy('id')->lockForUpdate()->get();
                $venueIds = $courts->pluck('venue_id')->map(fn ($id): int => (int) $id)->unique()->sort()->values();
                Venue::query()->whereIn('id', $venueIds)->orderBy('id')->lockForUpdate()->get();
                VenueSuspension::query()->whereIn('venue_id', $venueIds->all())->active()->orderBy('id')->lockForUpdate()->get();
                $locked = $this->release->lock($reservation);

                abort_unless($locked instanceof Reservation && $this->fingerprint($locked) === $before, 409,
                    'Đơn đã thay đổi trong lúc xử lý. Vui lòng tải lại và thử lại.');
                abort_unless($this->handleable($locked), 409, 'Đơn không còn ở trạng thái có thể xử lý.');
                $cases = AffectedReservationCase::query()->where('reservation_id', $locked->id)->open()->orderBy('id')->lockForUpdate()->get();
                abort_if($cases->isEmpty(), 409, 'Đơn không có case ảnh hưởng đang mở.');
                $slots = $this->unended($locked);
                abort_if($slots->isEmpty(), 409, 'Đơn không còn khung giờ chưa kết thúc để xử lý.');

                $from = ['court_id' => (int) $locked->court_id, 'slots' => $this->intervals($slots, iso: true)];
                $freed = $slots->map(fn (ReservationSlot $slot): array => [(int) $slot->court_id, $slot->starts_at->copy()])->all();
                $to = $apply($locked, $slots);

                $this->close($cases, $outcome, $actor, $contact);
                $this->notifications->recordAudit($actor->id, 'reservation.affected_'.$outcome, $locked, [
                    'reason' => $cases->map(fn (AffectedReservationCase $case): array => ['source_type' => $case->source_type, 'source_id' => (int) $case->source_id])->values()->all(),
                    'case_ids' => $cases->pluck('id')->all(),
                    'customer_agreed' => true,
                    'channel' => $contact['channel'],
                    'note' => $contact['note'] ?? null,
                    'from' => $from,
                    'to' => $to,
                ]);
                DB::afterCommit(fn () => $this->publish($courts, $locked, $outcome, $freed));

                return $locked->fresh(['court', 'slots']);
            });
        } catch (ValidationException $exception) {
            abort(409, collect($exception->errors())->flatten()->first() ?? 'Khung giờ đích không còn khả dụng.');
        } catch (QueryException $exception) {
            abort_if(Str::contains(strtolower($exception->getMessage()), ['reservation_slots_no_overlap', 'reservation_entitlements_active_unique']), 409,
                'Khung giờ đích không còn khả dụng.');
            throw $exception;
        }
    }

    /** The target court-time under the locks: court and venue open, venue not suspended, then the slot rules. */
    private function assertTarget(int $courtId, array $intervals, Reservation $locked, bool $allowStarted): void
    {
        $court = Court::query()->with('venue')->findOrFail($courtId);
        abort_unless($court->status === 'active', 409, 'Sân đích không hoạt động.');
        abort_unless($court->venue?->status === 'active', 409, 'Cơ sở của sân đích chưa hoạt động hoặc đang tạm ngừng.');
        abort_if($court->venue->isSuspended(), 409, 'Cơ sở của sân đích đang bị đình chỉ.');
        $this->policy->assertAllowed($court, $intervals, $locked, $allowStarted);
    }

    /** D8: a held booking holding a voucher is cancelled, not moved: confirmation re-checks the voucher on the new court-time. */
    private function assertNoHeldVoucher(Reservation $locked): void
    {
        abort_if($this->vouchers->holdsVoucher($locked), 409, 'Đơn đang giữ lượt voucher chờ thanh toán nên không đổi sân hay đổi giờ được; hãy huỷ đơn thay vì đổi.');
    }

    /** Held before its deadline, confirmed or in use; never completed or no-show. */
    private function handleable(Reservation $reservation): bool
    {
        $status = $reservation->status === 'confirmed' || $reservation->status === 'in_use'
            || ($reservation->status === 'held' && ($reservation->hold_expires_at === null || $reservation->hold_expires_at->isFuture()));

        return $status && ! in_array(data_get($reservation->metadata, 'operational_status'), ['completed', 'no_show'], true);
    }

    /** @return Collection<int, ReservationSlot> active slots that have not ended, earliest first */
    private function unended(Reservation $reservation): Collection
    {
        return ReservationSlot::query()->withoutGlobalScope('tenant_isolation')
            ->where('reservation_id', $reservation->id)
            ->whereIn('status', self::ACTIVE_SLOTS)
            ->where('ends_at', '>', now()->utc())
            ->orderBy('starts_at')
            ->get();
    }

    private function fingerprint(Reservation $reservation): string
    {
        $slots = ReservationSlot::query()->withoutGlobalScope('tenant_isolation')->where('reservation_id', $reservation->id)->orderBy('id')->get()
            ->map(fn (ReservationSlot $slot): string => implode('|', [$slot->id, $slot->court_id, $slot->status, $slot->starts_at?->getTimestamp(), $slot->ends_at?->getTimestamp()]));

        return implode(';', [$reservation->status, $reservation->court_id, ...$slots->all()]);
    }

    private function intervals(Collection $slots, bool $iso = false): array
    {
        return $slots->map(fn (ReservationSlot $slot): array => [
            'start' => $iso ? $slot->starts_at->toIso8601String() : $slot->starts_at,
            'end' => $iso ? $slot->ends_at->toIso8601String() : $slot->ends_at,
        ])->values()->all();
    }

    /** @param  Collection<int, AffectedReservationCase>  $cases */
    private function close(Collection $cases, string $outcome, User $actor, array $contact): void
    {
        AffectedReservationCase::query()->whereKey($cases->pluck('id')->all())->update([
            'status' => $outcome,
            'contact_channel' => $contact['channel'],
            'note' => $contact['note'] ?? null,
            'refund_note' => $contact['refund_note'] ?? null,
            'handled_by' => $actor->id,
            'handled_at' => now()->utc(),
            'updated_at' => now(),
        ]);
    }

    /** After commit: every locked court's new state, and waitlists for the court-time the booking left. */
    private function publish(Collection $courts, Reservation $reservation, string $outcome, array $freed): void
    {
        foreach ($courts as $court) {
            $holds = $outcome !== 'cancelled' && (int) $court->id === (int) $reservation->court_id;
            CourtStatusUpdated::dispatch((int) $court->venue_id, (int) $court->id, $holds ? 'booked' : 'available',
                ['reservation_id' => $reservation->id, 'code' => $reservation->code, 'action' => 'affected_'.$outcome]);
        }
        foreach ($freed as [$courtId, $startsAt]) {
            $this->waitlists->notifyForSlot($courtId, $startsAt);
        }
    }
}
