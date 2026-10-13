<?php

namespace Tests\Feature\Support;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\DisputeCase;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerRefundObligation;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\OwnerRefundService;
use App\Services\Reservation\LegacyBookingBackfillService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ReservationDisputeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $owner;

    private Venue $venue;

    private Court $court;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => 'Venue Owner']);
        $this->org = Organization::create(['name' => 'Dispute Org', 'slug' => 'rsv-dispute-'.uniqid(), 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $this->org->id, 'user_id' => $this->owner->id, 'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER, 'status' => 'active']);
        $this->venue = Venue::create(['organization_id' => $this->org->id, 'name' => 'Dispute Venue', 'slug' => 'rsv-dispute-venue-'.uniqid(), 'is_active' => true]);
        $this->court = Court::create(['venue_id' => $this->venue->id, 'name' => 'Court 1', 'is_active' => true]);
        $this->customer = User::factory()->create(['name' => 'Customer', 'role' => 'customer']);
    }

    public function test_booking_id_is_prohibited(): void
    {
        $this->file($this->customer, ['booking_id' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('booking_id');
    }

    public function test_stranger_cannot_file_on_someone_elses_reservation(): void
    {
        $reservation = $this->reservation(now()->subDays(2));

        $this->file(User::factory()->create(), ['reservation_id' => $reservation->id])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Unauthorized booking party.');
        $this->assertSame(0, DisputeCase::count());
    }

    public function test_owner_files_dispute_referencing_the_reservation(): void
    {
        $reservation = $this->reservation(now()->subDays(2));

        $res = $this->file($this->customer, ['reservation_id' => $reservation->id])->assertCreated();

        $res->assertJsonPath('data.reservation_id', $reservation->id)
            ->assertJsonPath('data.reservation_code', $reservation->code)
            ->assertJsonPath('data.booking_id', null)
            ->assertJsonPath('data.reference_type', 'reservation')
            ->assertJsonPath('data.reference_id', $reservation->id)
            ->assertJsonPath('data.organization_id', $this->org->id)
            ->assertJsonPath('data.venue_id', $this->venue->id)
            ->assertJsonPath('data.court_name', 'Court 1')
            ->assertJsonPath('data.amount_disputed', 250000);
    }

    public function test_entitlement_holder_can_file(): void
    {
        $holder = User::factory()->create();
        $reservation = $this->reservation(now()->subDays(2), $holder);

        $this->file($holder, ['reservation_id' => $reservation->id])
            ->assertCreated()
            ->assertJsonPath('data.reservation_id', $reservation->id);
    }

    public function test_duplicate_active_dispute_by_the_same_complainant_returns_409(): void
    {
        $holder = User::factory()->create();
        $reservation = $this->reservation(now()->subDays(2), $holder);
        $firstId = $this->file($this->customer, ['reservation_id' => $reservation->id])->assertCreated()->json('data.id');

        $this->file($this->customer, ['reservation_id' => $reservation->id])
            ->assertStatus(409)
            ->assertJsonPath('message', 'A dispute is already active for this booking.')
            ->assertJsonPath('dispute_id', $firstId)
            ->assertJsonPath('existing_dispute_id', $firstId);

        // The duplicate scope stays per complainant.
        $this->file($holder, ['reservation_id' => $reservation->id])->assertCreated();
    }

    public function test_filing_window_closes_30_days_after_the_last_slot(): void
    {
        $reservation = $this->reservation(now()->subDays(31));

        $this->file($this->customer, ['reservation_id' => $reservation->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Filing window has expired for this booking.');
    }

    public function test_legacy_mapped_reservation_is_accepted_and_keeps_its_booking_id(): void
    {
        $booking = Booking::create(['code' => 'BK-LEG-'.uniqid(), 'user_id' => $this->customer->id, 'court_id' => $this->court->id, 'total_amount' => 180000, 'status' => 'confirmed']);
        BookingSlot::create([
            'booking_id' => $booking->id,
            'court_id' => $this->court->id,
            'starts_at' => now()->subDays(3)->setTime(18, 0),
            'ends_at' => now()->subDays(3)->setTime(19, 0),
            'unit_price' => 180000,
            'status' => 'confirmed',
        ]);
        $reservationId = (int) app(LegacyBookingBackfillService::class)->backfillBooking($booking)->reservation_id;

        $id = $this->file($this->customer, ['reservation_id' => $reservationId])
            ->assertCreated()
            ->assertJsonPath('data.booking_id', $booking->id)
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.reservation_code', $booking->code)
            ->assertJsonPath('data.amount_disputed', 180000)
            ->json('data.id');

        $this->assertDatabaseHas('dispute_cases', ['id' => $id, 'reservation_id' => $reservationId, 'booking_id' => $booking->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson("/api/admin/disputes/{$id}")
            ->assertOk()
            ->assertJsonPath('data.reservation_code', $booking->code);
    }

    public function test_refund_ruling_on_a_mirrored_booking_uses_the_mirror_slots(): void
    {
        $booking = Booking::create(['code' => 'BK-ARB-'.uniqid(), 'user_id' => $this->customer->id, 'court_id' => $this->court->id, 'total_amount' => 100000, 'status' => 'confirmed', 'payment_model' => 'direct_owner_v1']);
        $bookingSlot = BookingSlot::create([
            'booking_id' => $booking->id,
            'court_id' => $this->court->id,
            'starts_at' => now()->subDays(2)->setTime(10, 0),
            'ends_at' => now()->subDays(2)->setTime(11, 0),
            'unit_price' => 100000,
            'status' => 'confirmed',
        ]);
        // Separate sequences: push mirror slot ids away from booking slot ids so a mixed-up id cannot pass by coincidence.
        DB::statement("SELECT setval(pg_get_serial_sequence('reservation_slots', 'id'), ?)", [$bookingSlot->id + 1000]);
        $mirror = Reservation::withoutGlobalScope('tenant_isolation')->findOrFail(app(LegacyBookingBackfillService::class)->backfillBooking($booking)->reservation_id);
        $mirrorSlotIds = $mirror->slots()->withoutGlobalScope('tenant_isolation')->pluck('id')->all();
        $disputeId = $this->file($this->customer, ['reservation_id' => $mirror->id])
            ->assertCreated()
            ->assertJsonPath('data.booking_id', $booking->id)
            ->json('data.id');

        $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson("/api/admin/disputes/{$disputeId}/arbitrate", [
            'decision' => 'resolve_and_refund',
            'refund_amount' => 100000,
            'refund_method' => 'owner_direct',
        ])->assertOk();

        $obligation = OwnerRefundObligation::withoutGlobalScope('tenant_isolation')->findOrFail(DisputeCase::findOrFail($disputeId)->refund_obligation_id);
        $this->assertSame($mirror->id, (int) $obligation->reservation_id);
        $this->assertSame($booking->id, (int) $obligation->booking_id);
        $this->assertSame($mirrorSlotIds, $obligation->slot_ids);
        $refunds = app(OwnerRefundService::class);
        $this->assertSame([$bookingSlot->id], $refunds->serialize($obligation)['legacy_slot_ids']->all());
        try {
            $refunds->quote($mirror, $this->customer, $mirrorSlotIds);
            $this->fail('A second refund on a slot the ruling already covers must be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    public function test_refund_ruling_on_a_reservation_only_dispute_links_the_reservation(): void
    {
        $reservation = $this->reservation(now()->subDays(2));
        $dispute = DisputeCase::create([
            'dispute_code' => 'DSP-RSV-REFUND',
            'reservation_id' => $reservation->id,
            'reference_type' => 'reservation',
            'reference_id' => $reservation->id,
            'dispute_type' => 'court_quality',
            'title' => 'Court flooded',
            'summary' => 'The court was flooded during the whole slot.',
            'status' => 'investigating',
            'complainant_id' => $this->customer->id,
            'complainant_name' => $this->customer->name,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson("/api/admin/disputes/{$dispute->id}/arbitrate", [
            'decision' => 'resolve_and_refund',
            'refund_amount' => 100000,
            'refund_method' => 'owner_direct',
        ])->assertOk();

        $obligation = OwnerRefundObligation::withoutGlobalScope('tenant_isolation')->findOrFail($dispute->fresh()->refund_obligation_id);
        $this->assertSame($reservation->id, (int) $obligation->reservation_id);
        $this->assertNull($obligation->booking_id);
        $this->assertSame($this->org->id, (int) $obligation->organization_id);
        $this->assertSame($this->venue->id, (int) $obligation->venue_id);
        $this->assertSame($this->owner->id, (int) $obligation->payer_id);
        $this->assertSame($reservation->slots()->pluck('id')->all(), $obligation->slot_ids);
    }

    public function test_is_disputed_matches_the_reservation_id_not_an_unrelated_booking_with_the_same_id(): void
    {
        $reservation = $this->reservation(now()->subDays(2));
        DB::table('bookings')->insert(['id' => $reservation->id, 'code' => 'BK-SAME-ID', 'user_id' => $this->customer->id, 'court_id' => $this->court->id, 'total_amount' => 1000, 'status' => 'confirmed', 'created_at' => now(), 'updated_at' => now()]);
        $base = ['dispute_type' => 'other', 'title' => 'Other', 'summary' => 'Unrelated complaint text.', 'status' => 'pending'];

        DisputeCase::create($base + ['dispute_code' => 'DSP-BOOKING-SAME-ID', 'booking_id' => $reservation->id]);
        $this->assertFalse($reservation->isDisputed());

        DisputeCase::create($base + ['dispute_code' => 'DSP-RESERVATION', 'reservation_id' => $reservation->id]);
        $this->assertTrue($reservation->isDisputed());
    }

    private function file(User $user, array $payload): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/disputes', $payload + [
            'title' => 'Sân trơn trượt',
            'summary' => 'Mặt sân ngập nước, không thể thi đấu an toàn.',
            'dispute_type' => 'payment_issue',
        ]);
    }

    private function reservation(Carbon $endsAt, ?User $holder = null): Reservation
    {
        $reservation = Reservation::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'code' => 'RSV-DSP-'.strtoupper(uniqid()),
            'status' => 'completed',
            'currency' => 'VND',
            'total_amount' => 250000,
            'payment_model' => 'direct_owner_v1',
        ]);
        $slot = ReservationSlot::create([
            'organization_id' => $this->org->id,
            'reservation_id' => $reservation->id,
            'court_id' => $this->court->id,
            'starts_at' => (clone $endsAt)->subHour(),
            'ends_at' => $endsAt,
            'unit_price' => 250000,
            'status' => 'confirmed',
            'snapshot' => [],
        ]);
        if ($holder) {
            ReservationEntitlement::create([
                'organization_id' => $this->org->id,
                'reservation_id' => $reservation->id,
                'reservation_slot_id' => $slot->id,
                'user_id' => $holder->id,
                'court_id' => $this->court->id,
                'starts_at' => $slot->starts_at,
                'ends_at' => $slot->ends_at,
                'amount' => 125000,
                'status' => 'active',
            ]);
        }

        return $reservation;
    }
}
