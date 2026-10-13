<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\CashShift;
use App\Models\Court;
use App\Models\LegacyBookingMapping;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PosProduct;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\Reservation\LegacyBookingBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LegacyBookingAuditAndBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function createLegacyBooking(Venue $venue, Court $court, User $user, int $amount = 150000): Booking
    {
        $booking = Booking::create([
            'code' => 'LEG-'.uniqid(),
            'user_id' => $user->id,
            'court_id' => $court->id,
            'total_amount' => $amount,
            'status' => 'confirmed',
            'payment_expires_at' => now()->addMinutes(30),
        ]);

        BookingSlot::create([
            'booking_id' => $booking->id,
            'court_id' => $court->id,
            'starts_at' => now()->addDay()->setHour(18)->setMinute(0)->setSecond(0),
            'ends_at' => now()->addDay()->setHour(19)->setMinute(0)->setSecond(0),
            'unit_price' => $amount,
            'status' => 'confirmed',
        ]);

        return $booking;
    }

    public function test_backfill_aligns_a_mirror_that_was_mapped_before_it_kept_the_booking_created_at(): void
    {
        $org = Organization::create(['name' => 'Org Drift', 'slug' => 'org-drift-'.uniqid()]);
        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'Drift Arena', 'slug' => 'drift-'.uniqid()]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Court 1']);
        $booking = $this->createLegacyBooking($venue, $court, User::factory()->create());
        $reservationId = app(LegacyBookingBackfillService::class)->backfillBooking($booking)->reservation_id;
        DB::table('reservations')->where('id', $reservationId)->update(['created_at' => now()->addMonth()]);

        $this->artisan('v2:backfill-legacy-bookings', ['--organization' => $org->id, '--dry-run' => true])->expectsOutputToContain('"created_at_drift": 1')->assertExitCode(0);
        $this->artisan('v2:backfill-legacy-bookings', ['--organization' => $org->id])->expectsOutputToContain('"created_at_aligned": 1')->assertExitCode(0);
        $this->assertSame(
            $booking->fresh()->created_at->toDateTimeString(),
            Reservation::withoutGlobalScope('tenant_isolation')->findOrFail($reservationId)->created_at->toDateTimeString(),
        );
        $this->artisan('v2:backfill-legacy-bookings', ['--organization' => $org->id, '--audit' => true])->expectsOutputToContain('"created_at_drift": 0')->assertExitCode(0);
    }

    public function test_audit_and_dry_run_reports_counts_orphans_and_collisions_without_writing_records(): void
    {
        $org = Organization::create(['name' => 'Org Alpha', 'slug' => 'org-alpha-'.uniqid()]);
        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'Alpha Stadium', 'slug' => 'alpha-'.uniqid()]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Court 1']);
        $user = User::factory()->create();

        // 1. Valid booking
        $validBooking = $this->createLegacyBooking($venue, $court, $user, 200000);

        // 2. Orphan booking: Court with NO organization owner
        $orphanVenue = Venue::create(['organization_id' => null, 'name' => 'Orphan Venue', 'slug' => 'orphan-'.uniqid()]);
        $orphanCourt = Court::create(['venue_id' => $orphanVenue->id, 'name' => 'Orphan Court']);
        $orphanBooking1 = Booking::create([
            'code' => 'ORPHAN-1',
            'user_id' => $user->id,
            'court_id' => $orphanCourt->id,
            'total_amount' => 100000,
            'status' => 'confirmed',
        ]);
        BookingSlot::create([
            'booking_id' => $orphanBooking1->id,
            'court_id' => $orphanCourt->id,
            'starts_at' => now()->addDay()->setHour(10),
            'ends_at' => now()->addDay()->setHour(11),
            'unit_price' => 100000,
            'status' => 'confirmed',
        ]);

        // 3. Orphan booking: 0 slots
        $orphanBooking2 = Booking::create([
            'code' => 'ORPHAN-2',
            'user_id' => $user->id,
            'court_id' => $court->id,
            'total_amount' => 50000,
            'status' => 'confirmed',
        ]);

        $backfill = app(LegacyBookingBackfillService::class);

        // Run Audit
        $audit = $backfill->audit();

        $this->assertTrue($audit['dry_run']);
        $this->assertSame(3, $audit['legacy_bookings_total']);
        $this->assertSame(2, $audit['orphan_count']);
        $this->assertSame(1, $audit['pending_count']);
        $this->assertSame(0, $audit['mapped_count']);

        // Assert database was NOT touched
        $this->assertSame(0, Reservation::count());
        $this->assertSame(0, LegacyBookingMapping::count());

        // Run backfill with dry-run = true
        $dryRunResult = $backfill->backfill(null, dryRun: true);
        $this->assertTrue($dryRunResult['dry_run']);
        $this->assertSame(0, $dryRunResult['created']);
        $this->assertSame(0, Reservation::count());
    }

    public function test_backfill_skips_orphans_and_creates_canonical_mappings(): void
    {
        $org = Organization::create(['name' => 'Org Beta', 'slug' => 'org-beta-'.uniqid()]);
        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'Beta Arena', 'slug' => 'beta-'.uniqid()]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Beta Court 1']);
        $user = User::factory()->create();

        $validBooking = $this->createLegacyBooking($venue, $court, $user, 180000);

        // Orphan booking: 0 slots
        Booking::create([
            'code' => 'ORPHAN-NO-SLOTS',
            'user_id' => $user->id,
            'court_id' => $court->id,
            'total_amount' => 50000,
            'status' => 'confirmed',
        ]);

        $backfill = app(LegacyBookingBackfillService::class);
        $result = $backfill->backfill($org->id, dryRun: false);

        $this->assertFalse($result['dry_run']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['orphan_count']);

        // Exactly one canonical reservation and mapping was created
        $this->assertSame(1, Reservation::where('organization_id', $org->id)->count());
        $this->assertSame(1, LegacyBookingMapping::where('organization_id', $org->id)->count());

        $mapping = LegacyBookingMapping::where('legacy_booking_id', $validBooking->id)->first();
        $this->assertNotNull($mapping);
        $this->assertSame($validBooking->code, $mapping->legacy_code);
        $this->assertSame(180000, $mapping->legacy_total_amount);
    }

    public function test_collision_detected_when_legacy_booking_checksum_changes(): void
    {
        $org = Organization::create(['name' => 'Org Gamma', 'slug' => 'org-gamma-'.uniqid()]);
        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'Gamma Center', 'slug' => 'gamma-'.uniqid()]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Gamma Court 1']);
        $user = User::factory()->create();

        $booking = $this->createLegacyBooking($venue, $court, $user, 120000);

        $backfill = app(LegacyBookingBackfillService::class);
        $backfill->backfill($org->id, dryRun: false);

        // Tamper with legacy booking after mapping
        $booking->update(['total_amount' => 999999]);

        // Audit detects the collision
        $audit = $backfill->audit($org->id);
        $this->assertSame(1, $audit['collision_count']);
        $this->assertSame($booking->id, $audit['collisions'][0]['legacy_booking_id']);
        $this->assertStringContainsString('checksum mismatch', $audit['collisions'][0]['reason']);
    }

    public function test_backfill_reports_collisions_instead_of_throwing(): void
    {
        $org = Organization::create(['name' => 'Org Gamma 2', 'slug' => 'org-gamma2-'.uniqid()]);
        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'Gamma 2 Center', 'slug' => 'gamma2-'.uniqid()]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Gamma 2 Court 1']);
        $booking = $this->createLegacyBooking($venue, $court, User::factory()->create(), 120000);
        $backfill = app(LegacyBookingBackfillService::class);
        $backfill->backfill($org->id);
        $booking->update(['total_amount' => 999999]);

        $result = $backfill->backfill($org->id);

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['existing']);
        $this->assertSame([$booking->id], array_column($result['collisions'], 'legacy_booking_id'));
        $this->assertFalse($result['valid']);
        $this->artisan('v2:backfill-legacy-bookings', ['--organization' => $org->id])->assertExitCode(1);
    }

    public function test_mapping_failure_becomes_an_orphan_and_the_run_continues(): void
    {
        $org = Organization::create(['name' => 'Org Zeta', 'slug' => 'org-zeta-'.uniqid()]);
        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'Zeta Arena', 'slug' => 'zeta-'.uniqid()]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Zeta Court']);
        $user = User::factory()->create();
        // The first booking's slot overlaps a confirmed V2 slot, so its mirror violates reservation_slots_no_overlap.
        $blocked = $this->createLegacyBooking($venue, $court, $user, 150000);
        $slot = $blocked->slots()->firstOrFail();
        $reservation = Reservation::create([
            'organization_id' => $org->id, 'user_id' => $user->id, 'court_id' => $court->id, 'code' => 'RSV-ZETA-'.uniqid(),
            'status' => 'confirmed', 'currency' => 'VND', 'total_amount' => 150000, 'payment_model' => 'legacy_wallet_v1',
        ]);
        ReservationSlot::create([
            'organization_id' => $org->id, 'reservation_id' => $reservation->id, 'court_id' => $court->id,
            'starts_at' => $slot->starts_at, 'ends_at' => $slot->ends_at, 'unit_price' => 150000, 'status' => 'confirmed',
        ]);
        $mappable = $this->createLegacyBooking($venue, Court::create(['venue_id' => $venue->id, 'name' => 'Zeta Court 2']), $user, 90000);

        $result = app(LegacyBookingBackfillService::class)->backfill($org->id);

        $this->assertSame(1, $result['created']);
        $this->assertSame([$blocked->id], array_column($result['orphans'], 'legacy_booking_id'));
        $this->assertStringStartsWith('Mapping failed: ', $result['orphans'][0]['reason']);
        $this->assertStringContainsString('reservation_slots_no_overlap', $result['orphans'][0]['reason']);
        $this->assertFalse(LegacyBookingMapping::where('legacy_booking_id', $blocked->id)->exists());
        $this->assertFalse(Reservation::where('code', 'LEGACY-'.$blocked->code)->exists());
        $this->assertTrue(LegacyBookingMapping::where('legacy_booking_id', $mappable->id)->exists());
    }

    public function test_mirror_reservation_keeps_the_booking_created_at(): void
    {
        $org = Organization::create(['name' => 'Org Eta', 'slug' => 'org-eta-'.uniqid()]);
        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'Eta Arena', 'slug' => 'eta-'.uniqid()]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Eta Court']);
        $booking = $this->createLegacyBooking($venue, $court, User::factory()->create());
        DB::table('bookings')->where('id', $booking->id)->update(['created_at' => '2026-09-01 08:30:00']);

        app(LegacyBookingBackfillService::class)->backfillBooking($booking->fresh());

        $reservationId = LegacyBookingMapping::where('legacy_booking_id', $booking->id)->value('reservation_id');
        $this->assertSame('2026-09-01 08:30:00', Reservation::findOrFail($reservationId)->created_at->format('Y-m-d H:i:s'));
    }

    public function test_real_backfill_run_relinks_reviews_and_disputes_written_by_booking_id(): void
    {
        $org = Organization::create(['name' => 'Org Theta', 'slug' => 'org-theta-'.uniqid()]);
        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'Theta Arena', 'slug' => 'theta-'.uniqid()]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Theta Court']);
        $user = User::factory()->create();
        $booking = $this->createLegacyBooking($venue, $court, $user);
        DB::table('reviews')->insert(['user_id' => $user->id, 'court_id' => $court->id, 'booking_id' => $booking->id, 'rating' => 5, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('dispute_cases')->insert(['dispute_code' => 'DSP-RELINK-1', 'booking_id' => $booking->id, 'court_name' => 'Theta Court', 'dispute_type' => 'billing', 'title' => 'Charge', 'summary' => 'Charged twice', 'complainant_name' => 'A', 'respondent_name' => 'B', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame(0, Artisan::call('v2:backfill-legacy-bookings', ['--dry-run' => true]));
        $this->assertArrayNotHasKey('reviews_relinked', json_decode(Artisan::output(), true));

        $this->assertSame(0, Artisan::call('v2:backfill-legacy-bookings'));
        $output = json_decode(Artisan::output(), true);

        $this->assertSame(1, $output['reviews_relinked']);
        $this->assertSame(1, $output['disputes_relinked']);
        $reservationId = LegacyBookingMapping::where('legacy_booking_id', $booking->id)->value('reservation_id');
        $this->assertSame($reservationId, (int) DB::table('reviews')->value('reservation_id'));
        $this->assertSame($reservationId, (int) DB::table('dispute_cases')->value('reservation_id'));
    }

    public function test_no_numerical_id_coincidence_fallback_in_resolution_and_pos(): void
    {
        $org = Organization::create(['name' => 'Org Delta', 'slug' => 'org-delta-'.uniqid()]);
        $cashier = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $cashier->id,
            'role' => OrganizationMember::ROLE_CASHIER,
            'status' => 'active',
        ]);

        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'Delta Arena', 'slug' => 'delta-'.uniqid()]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Delta Court']);
        $user = User::factory()->create();

        config([
            'features.pilot_organizations' => [(string) $org->id],
            'features.flags.tenant_pos_and_wallet' => true,
        ]);

        // 1. Create product and open shift
        $product = PosProduct::create([
            'organization_id' => $org->id,
            'sku' => 'BALL-1',
            'name' => 'Pickleball Ball',
            'price' => 30000,
            'stock_quantity' => 20,
            'is_active' => true,
        ]);

        CashShift::create([
            'organization_id' => $org->id,
            'cashier_id' => $cashier->id,
            'opening_cash' => 500000,
            'status' => 'open',
            'opened_at' => now(),
        ]);
        // 2. Unmapped legacy booking ID: 8888
        $backfill = app(LegacyBookingBackfillService::class);
        try {
            $backfill->resolveCanonicalReservationId(8888, $org->id);
            $this->fail('Expected HttpException 422 was not thrown for unmapped legacy booking ID.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('has no canonical reservation mapping', $e->getMessage());
        }

        // 3. Submitting unmapped reservation_id in POS checkout fails with 422 (no ID coincidence fallback)
        $posFailRes = $this->actingAs($cashier)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->withHeader('Idempotency-Key', uniqid('pos-fail-'))
            ->postJson('/api/pos/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
                'reservation_id' => 8888, // Unmapped ID
                'payment_method' => 'cash',
            ]);
        $posFailRes->assertStatus(422)
            ->assertJsonPath('message', 'Legacy booking 8888 has no canonical reservation mapping.');

        // 4. Now create and backfill a real legacy booking
        $booking = $this->createLegacyBooking($venue, $court, $user, 100000);
        $backfill->backfillBooking($booking);

        $mapping = LegacyBookingMapping::where('legacy_booking_id', $booking->id)->first();
        $this->assertNotNull($mapping);

        // Resolution of the mapped legacy booking ID explicitly resolves to canonical reservation ID
        $resolvedId = $backfill->resolveCanonicalReservationId($booking->id, $org->id);
        $this->assertSame($mapping->reservation_id, $resolvedId);

        // 5. POS order with the mapped legacy booking ID succeeds and attaches to canonical reservation
        $posSuccessRes = $this->actingAs($cashier)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->withHeader('Idempotency-Key', uniqid('pos-success-'))
            ->postJson('/api/pos/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
                'reservation_id' => $booking->id,
                'payment_method' => 'cash',
            ]);
        $posSuccessRes->assertStatus(201)
            ->assertJsonPath('order.reservation_id', $mapping->reservation_id);
    }

    public function test_console_command_runs_audit_and_dry_run_modes(): void
    {
        $org = Organization::create(['name' => 'Org Epsilon', 'slug' => 'org-eps-'.uniqid()]);
        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'Epsilon Park', 'slug' => 'eps-'.uniqid()]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Eps Court']);
        $user = User::factory()->create();

        $this->createLegacyBooking($venue, $court, $user, 150000);

        // 1. Run artisan command with --audit
        $exitAudit = Artisan::call('v2:backfill-legacy-bookings', [
            '--organization' => (string) $org->id,
            '--audit' => true,
        ]);
        $this->assertSame(0, $exitAudit);
        $outputAudit = json_decode(Artisan::output(), true);
        $this->assertTrue($outputAudit['dry_run']);
        $this->assertSame(1, $outputAudit['legacy_bookings_total']);
        $this->assertSame(1, $outputAudit['pending_count']);

        // 2. Run artisan command with --dry-run
        $exitDryRun = Artisan::call('v2:backfill-legacy-bookings', [
            '--organization' => (string) $org->id,
            '--dry-run' => true,
        ]);
        $this->assertSame(0, $exitDryRun);
        $outputDryRun = json_decode(Artisan::output(), true);
        $this->assertTrue($outputDryRun['dry_run']);
        $this->assertSame(0, $outputDryRun['created']);

        // 3. Run artisan command actual backfill
        $exitRun = Artisan::call('v2:backfill-legacy-bookings', [
            '--organization' => (string) $org->id,
        ]);
        $this->assertSame(0, $exitRun);
        $outputRun = json_decode(Artisan::output(), true);
        $this->assertFalse($outputRun['dry_run']);
        $this->assertSame(1, $outputRun['created']);
    }
}
