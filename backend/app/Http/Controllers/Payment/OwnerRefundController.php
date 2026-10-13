<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\LegacyBookingMapping;
use App\Models\LegacyBookingSlotMapping;
use App\Models\OrganizationMember;
use App\Models\OwnerRefundObligation;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\OwnerRefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerRefundController extends Controller
{
    use ResolvesOrganization;

    public function quote(Request $request, string $reservation, OwnerRefundService $refunds): JsonResponse
    {
        $record = $this->findReservation($reservation);
        $this->authorizeParty($request->user(), $record);
        $data = $request->validate([
            'slot_ids' => ['required', 'array', 'min:1', 'max:24'],
            'slot_ids.*' => ['required', 'integer', 'min:1'],
        ]);
        $slotIds = $this->canonicalSlotIds($reservation, $record, $data['slot_ids']);

        return response()->json(['data' => $refunds->quote($record, $request->user(), $slotIds)]);
    }

    public function request(Request $request, string $reservation, OwnerRefundService $refunds): JsonResponse
    {
        $record = $this->findReservation($reservation);
        $this->authorizeParty($request->user(), $record);
        $data = $request->validate([
            'slot_ids' => ['required', 'array', 'min:1', 'max:24'],
            'slot_ids.*' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'recipient' => ['nullable', 'array'],
            'recipient.bank_code' => ['required_with:recipient', 'string', 'max:32'],
            'recipient.account_name' => ['required_with:recipient', 'string', 'max:160'],
            'recipient.account_number' => ['required_with:recipient', 'string', 'max:34'],
        ]);
        $recipient = $data['recipient'] ?? [];
        $obligation = $refunds->request(
            $record,
            $request->user(),
            $this->canonicalSlotIds($reservation, $record, $data['slot_ids']),
            $data['reason'],
            $recipient,
            $data['idempotency_key'],
        );

        return response()->json(['data' => $refunds->serialize($obligation->load('reservation'))], 201);
    }

    public function index(Request $request, string $reservation, OwnerRefundService $refunds): JsonResponse
    {
        $record = $this->findReservation($reservation);
        $this->authorizeParty($request->user(), $record);
        $isOwner = $this->isOwnerForReservation($request->user(), $record);
        $query = OwnerRefundObligation::query()
            ->where('reservation_id', $record->id)
            ->with(['reservation', 'allocations.paymentAllocation', 'evidence']);
        if (! $isOwner) {
            $query->where('payer_id', $request->user()->id);
        }

        return response()->json(['data' => $query->orderByDesc('id')->get()->map(fn (OwnerRefundObligation $refund) => $refunds->serialize($refund))->values()]);
    }

    public function inbox(Request $request, OwnerRefundService $refunds): JsonResponse
    {
        $organization = $this->organization($request);
        abort_unless($this->isOrganizationOperator($request, $organization), 403, 'Only venue owners or managers can review refund obligations.');
        $query = OwnerRefundObligation::query()
            ->where('organization_id', $organization->id)
            ->with(['reservation', 'allocations.paymentAllocation', 'evidence'])
            ->orderByDesc('id');
        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        return response()->json(['data' => $query->limit(100)->get()->map(fn (OwnerRefundObligation $o) => $refunds->serialize($o))->values()]);
    }

    public function approve(Request $request, int $refund, OwnerRefundService $refunds): JsonResponse
    {
        $record = $this->findRefund($refund);
        $updated = $refunds->approve($record, $request->user());

        return response()->json(['data' => $refunds->serialize($updated->load('reservation'))]);
    }

    public function reject(Request $request, int $refund, OwnerRefundService $refunds): JsonResponse
    {
        $record = $this->findRefund($refund);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $updated = $refunds->reject($record, $request->user(), $data['reason']);

        return response()->json(['data' => $refunds->serialize($updated->load('reservation'))]);
    }

    public function reportExecution(Request $request, int $refund, OwnerRefundService $refunds): JsonResponse
    {
        $record = $this->findRefund($refund);
        $data = $request->validate([
            'transfer_reference' => ['required', 'string', 'max:160'],
            'evidence' => ['required', 'string', 'max:4000'],
        ]);
        $updated = $refunds->reportExecution($record, $request->user(), $data['transfer_reference'], $data['evidence']);

        return response()->json(['data' => $refunds->serialize($updated->load('reservation'))]);
    }

    public function acknowledge(Request $request, int $refund, OwnerRefundService $refunds): JsonResponse
    {
        $record = $this->findRefund($refund);
        $data = $request->validate([
            'accepted' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $updated = $refunds->acknowledge($record, $request->user(), (bool) $data['accepted'], $data['note'] ?? null);

        return response()->json(['data' => $refunds->serialize($updated->load('reservation'))]);
    }

    private function findReservation(string $identifier): Reservation
    {
        $reservation = Reservation::query()->withoutGlobalScope('tenant_isolation')->where('code', $identifier)->first();
        if ($reservation) {
            return $reservation;
        }

        $booking = Booking::query()->where('code', $identifier)->firstOrFail();
        $mapping = LegacyBookingMapping::query()->withoutGlobalScope('tenant_isolation')->where('legacy_booking_id', $booking->id)->firstOrFail();

        return Reservation::query()->withoutGlobalScope('tenant_isolation')->findOrFail($mapping->reservation_id);
    }

    private function canonicalSlotIds(string $identifier, Reservation $reservation, array $slotIds): array
    {
        $booking = Booking::query()->where('code', $identifier)->first();
        if (! $booking) {
            return $slotIds;
        }

        $mapping = LegacyBookingMapping::query()->withoutGlobalScope('tenant_isolation')->where('legacy_booking_id', $booking->id)->firstOrFail();
        $slotMappings = LegacyBookingSlotMapping::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('legacy_booking_mapping_id', $mapping->id)
            ->whereIn('legacy_booking_slot_id', array_map('intval', $slotIds))
            ->get();
        abort_unless($slotMappings->count() === count(array_unique(array_map('intval', $slotIds))), 404);
        abort_unless((int) $mapping->reservation_id === (int) $reservation->id, 404);

        return $slotMappings->pluck('reservation_slot_id')->map(fn ($id) => (int) $id)->all();
    }

    private function findRefund(int $id): OwnerRefundObligation
    {
        $refund = OwnerRefundObligation::query()->withoutGlobalScope('tenant_isolation')->with(['reservation.court.venue', 'allocations.paymentAllocation', 'evidence'])->findOrFail($id);
        $this->authorizeParty(request()->user(), $refund->reservation);

        return $refund;
    }

    private function authorizeParty(User $user, Reservation $reservation): void
    {
        abort_unless(
            (int) $reservation->user_id === (int) $user->id || $this->isOwnerForReservation($user, $reservation),
            404
        );
    }

    private function isOwnerForReservation(User $user, Reservation $reservation): bool
    {
        $venue = $reservation->court?->venue ?? Venue::find($reservation->court()->value('venue_id'));

        return $venue !== null
            && $user->hasRoleInOrganization((int) $reservation->organization_id, OrganizationMember::ROLE_ORGANIZATION_OWNER)
            && $user->canManageVenue($venue);
    }
}
