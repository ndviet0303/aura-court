<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Reservation;
use App\Services\LegacyBookingBackfillService;
use App\Services\TenantContext;
use Illuminate\Http\Request;

class LegacyBookingAdapterController extends Controller
{
    public function __construct(private readonly LegacyBookingBackfillService $backfill) {}

    public function sync(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        $mapping = $this->backfill->backfillBooking($booking);

        return response()->json([
            'mapping' => $mapping->load(['reservation.slots']),
            'message' => 'Legacy booking is available through the v2 compatibility projection.',
        ], 201);
    }

    public function projection(Request $request, string $reservation)
    {
        $user = $request->user();
        abort_unless($user, 401);
        $organization = app(TenantContext::class)->getOrganization();
        if (! $organization) {
            $organization = $user->organizations()->where('organizations.status', 'active')->first();
            app(TenantContext::class)->setOrganization($organization);
        }
        abort_unless($organization, 400, 'Organization context required.');

        $query = Reservation::query()->where('organization_id', $organization->id);
        $query->where(ctype_digit($reservation) ? 'id' : 'code', ctype_digit($reservation) ? (int) $reservation : $reservation);
        $record = $query->with(['slots', 'legacyMapping'])->firstOrFail();
        abort_unless($user->isAdmin() || $record->user_id === $user->id || $organization->hasMember($user->id), 403);

        return response()->json($this->backfill->project($record));
    }
}
