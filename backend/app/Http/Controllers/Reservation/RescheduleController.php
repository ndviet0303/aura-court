<?php

namespace App\Http\Controllers\Reservation;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\LegacyBookingMapping;
use App\Models\OrganizationMember;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Services\Reservation\RescheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RescheduleController extends Controller
{
    /**
     * Server preview of a proposed change: deadline, fee, extra/refund amount and
     * replacement availability. No inventory is secured and no payment is created.
     */
    public function preview(Request $request, string $reservation, RescheduleService $reschedules): JsonResponse
    {
        $record = $this->findReservation($reservation);
        $this->authorizeParty($request->user(), $record);
        $data = $request->validate([
            'court_id' => ['required', 'integer', 'min:1'],
            'slots' => ['required', 'array', 'min:1', 'max:24'],
            'slots.*' => ['required'],
        ]);

        return response()->json([
            'data' => $reschedules->preview($record, $request->user(), (int) $data['court_id'], $data['slots']),
        ]);
    }

    /**
     * Request the change. Secures the replacement hold and either collects the
     * verified difference (more expensive) or swaps atomically and records the
     * refund difference (cheaper/equal). Idempotent per `idempotency_key`.
     */
    public function request(Request $request, string $reservation, RescheduleService $reschedules): JsonResponse
    {
        $record = $this->findReservation($reservation);
        $this->authorizeParty($request->user(), $record);
        $data = $request->validate([
            'court_id' => ['required', 'integer', 'min:1'],
            'slots' => ['required', 'array', 'min:1', 'max:24'],
            'slots.*' => ['required'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'recipient' => ['nullable', 'array'],
            'recipient.bank_code' => ['required_with:recipient', 'string', 'max:32'],
            'recipient.account_name' => ['required_with:recipient', 'string', 'max:160'],
            'recipient.account_number' => ['required_with:recipient', 'string', 'max:34'],
        ]);

        $result = $reschedules->request(
            $record,
            $request->user(),
            (int) $data['court_id'],
            $data['slots'],
            $data['idempotency_key'],
            $data['recipient'] ?? [],
        );

        return response()->json(['data' => $result], 201);
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

    private function authorizeParty(User $user, Reservation $reservation): void
    {
        $venue = $reservation->court?->venue ?? Venue::find($reservation->court()->value('venue_id'));
        $isOwner = $venue !== null
            && $user->hasRoleInOrganization((int) $reservation->organization_id, OrganizationMember::ROLE_ORGANIZATION_OWNER)
            && $user->canManageVenue($venue);

        abort_unless((int) $reservation->user_id === (int) $user->id || $isOwner, 404);
    }
}
