<?php

namespace Tests\Feature\Module06;

use App\Models\Court;
use App\Models\User;
use App\Models\VenuePackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RecordsOwnerPayments;
use Tests\Feature\Module06\Concerns\OperatesBookings;
use Tests\TestCase;

/** FR-102, BR-119, BR-122, BR-138: who may open a venue's operations and a booking, and what they see. */
class OperationsScopeTest extends TestCase
{
    use OperatesBookings, RecordsOwnerPayments {
        OperatesBookings::booking insteadof RecordsOwnerPayments;
    }
    use RefreshDatabase;

    private Court $court;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo($this->at('08:00'));
        $this->court = $this->bookableCourt();
        $this->staff = $this->staffOf($this->court->venue);
    }

    public function test_fr_102_br_122_staff_see_the_booking_amount_and_payment_result_without_receiver_details(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));
        $this->receipt($this->court->venue, 100000, $this->at('08:00'), $booking);

        $response = $this->actingAs($this->staff)
            ->getJson("/api/attendance/AURA_BOOKING_{$booking->code}", ['X-Organization-ID' => (string) $booking->organization_id])
            ->assertOk()
            ->assertJsonPath('reservation.code', $booking->code)
            ->assertJsonPath('reservation.court.code', $this->court->code)
            ->assertJsonPath('reservation.venue.name', $this->court->venue->name)
            ->assertJsonPath('reservation.starts_at', $this->at('10:00')->utc()->toIso8601String())
            ->assertJsonPath('reservation.ends_at', $this->at('11:00')->utc()->toIso8601String())
            ->assertJsonPath('reservation.total_amount', 100000)
            ->assertJsonPath('reservation.operational_status', 'confirmed')
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('payment.amount', 100000);

        $this->assertNotNull($response->json('reservation.customer_name'));
        foreach (['account_number', 'account_name', 'bank', 'receiver', 'staff_note'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $response->getContent());
        }
    }

    public function test_br_119_staff_of_another_venue_and_other_organizations_see_nothing(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));
        $venue = $this->court->venue;
        $otherVenueStaff = $this->staffOf($this->openVenue(['organization_id' => $venue->organization_id]));
        $elsewhere = $this->openVenue();
        $outsider = $this->ownerOf($elsewhere->organization);
        $header = ['X-Organization-ID' => (string) $venue->organization_id];

        $this->actingAs($otherVenueStaff);
        $this->getJson("/api/operations/venues/{$venue->id}/day?date=2026-11-03", $header)
            ->assertForbidden()->assertJsonPath('code', 'VENUE_NOT_ASSIGNED');
        $this->getJson("/api/operations/venues/{$venue->id}/in-use", $header)
            ->assertForbidden()->assertJsonPath('code', 'VENUE_NOT_ASSIGNED');
        $this->getJson("/api/attendance/{$booking->code}", $header)
            ->assertForbidden()->assertJsonPath('code', 'VENUE_NOT_ASSIGNED');

        $this->actingAs($outsider);
        $this->getJson("/api/operations/venues/{$venue->id}/day?date=2026-11-03", $header)->assertForbidden();
        $own = ['X-Organization-ID' => (string) $elsewhere->organization_id];
        $this->getJson("/api/operations/venues/{$venue->id}/day?date=2026-11-03", $own)
            ->assertNotFound()->assertJsonPath('code', 'VENUE_NOT_FOUND');
        $this->getJson("/api/attendance/{$booking->code}", $own)
            ->assertNotFound()->assertJsonPath('code', 'BOOKING_NOT_FOUND');

        $this->actingAs($this->ownerOf($venue->organization))
            ->getJson("/api/operations/venues/{$venue->id}/day?date=2026-11-03", $header)
            ->assertOk()->assertJsonPath('reservations.0.code', $booking->code);
    }

    public function test_br_138_an_ended_venue_package_does_not_stop_check_in_or_check_out(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));
        VenuePackage::query()->where('venue_id', $this->court->venue_id)->update(['ends_at' => $this->at('07:00')]);

        $this->travelTo($this->at('10:00'));
        $this->attend($this->staff, 'check-in', $booking)->assertCreated();
        $this->actingAs($this->staff)
            ->getJson("/api/operations/venues/{$this->court->venue_id}/in-use", ['X-Organization-ID' => (string) $booking->organization_id])
            ->assertOk()->assertJsonPath('reservations.0.code', $booking->code);
        $this->travelTo($this->at('11:00'));
        $this->attend($this->staff, 'check-out', $booking)->assertOk();
    }
}
