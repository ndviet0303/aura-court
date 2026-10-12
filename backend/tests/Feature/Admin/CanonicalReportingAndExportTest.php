<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Court;
use App\Models\LegacyBookingMapping;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerRefundObligation;
use App\Models\PlatformPaymentAttempt;
use App\Models\PosOrder;
use App\Models\Reservation;
use App\Models\SportZone;
use App\Models\User;
use App\Models\Venue;
use App\Services\Reservation\LegacyBookingBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class CanonicalReportingAndExportTest extends TestCase
{
    use RefreshDatabase;

    private User $platformAdmin;

    private User $ownerAlpha;

    private User $customer;

    private Organization $orgAlpha;

    private Organization $orgBeta;

    private Venue $venueAlpha;

    private Venue $venueBeta;

    private Court $courtAlpha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platformAdmin = User::factory()->create([
            'email' => 'platform-admin@aura.local',
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->ownerAlpha = User::factory()->create([
            'email' => 'owner-alpha@aura.local',
            'role' => 'customer',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        $this->customer = User::factory()->create([
            'email' => 'customer@aura.local',
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        $this->orgAlpha = Organization::create([
            'name' => 'Org Alpha',
            'slug' => 'org-alpha-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        $this->orgBeta = Organization::create([
            'name' => 'Org Beta',
            'slug' => 'org-beta-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        OrganizationMember::create([
            'organization_id' => $this->orgAlpha->id,
            'user_id' => $this->ownerAlpha->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        $this->venueAlpha = Venue::create([
            'organization_id' => $this->orgAlpha->id,
            'name' => ['vi' => 'Sân Alpha', 'en' => 'Alpha Stadium'],
            'slug' => 'alpha-'.uniqid(),
            'address' => '123 Alpha Street',
            'status' => 'published',
            'is_active' => true,
        ]);

        $this->venueBeta = Venue::create([
            'organization_id' => $this->orgBeta->id,
            'name' => ['vi' => 'Sân Beta', 'en' => 'Beta Stadium'],
            'slug' => 'beta-'.uniqid(),
            'address' => '456 Beta Street',
            'status' => 'published',
            'is_active' => true,
        ]);

        $zone = SportZone::create([
            'venue_id' => $this->venueAlpha->id,
            'name' => ['vi' => 'Khu Cầu Lông', 'en' => 'Badminton Zone'],
            'sport_type' => 'badminton',
            'slot_duration' => 60,
            'is_active' => true,
        ]);

        $this->courtAlpha = Court::create([
            'venue_id' => $this->venueAlpha->id,
            'sport_zone_id' => $zone->id,
            'name' => ['vi' => 'Sân 1', 'en' => 'Court 1'],
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Deduplicates mapped V1 and V2 bookings without double counting.
     * Create a V1 Booking and a V2 Reservation with the same code. Verify the report counts 1 booking and 1x revenue.
     */
    public function test_report_deduplicates_mapped_v1_and_v2_bookings_without_double_counting(): void
    {
        $code = 'BK-CANONICAL-001';

        $v1Booking = Booking::create([
            'court_id' => $this->courtAlpha->id,
            'user_id' => $this->customer->id,
            'code' => $code,
            'status' => 'confirmed',
            'total_amount' => 300000,
        ]);

        $v2Reservation = Reservation::create([
            'organization_id' => $this->orgAlpha->id,
            'court_id' => $this->courtAlpha->id,
            'user_id' => $this->customer->id,
            'code' => $code,
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 300000,
            'confirmed_at' => now(),
            'metadata' => ['booking_id' => $v1Booking->id],
        ]);

        $response = $this->actingAs($this->ownerAlpha)
            ->getJson('/api/v1/admin/reports?from='.now()->startOfMonth()->toDateString().'&to='.now()->endOfMonth()->toDateString());

        $response->assertOk();
        $response->assertJsonPath('bookings', 1);
        $response->assertJsonPath('confirmed', 1);
        $response->assertJsonPath('booking_revenue', 300000);
        $response->assertJsonPath('owner_revenue', 300000);
        $response->assertJsonPath('revenue', 300000);
    }

    /**
     * D48 Authoritative Legacy Mapping Dedup:
     * Deduplicates via LegacyBookingMapping when codes differ and no metadata link exists.
     * Create a V1 Booking with code 'BK-LEGACY-DIFF-001' and a V2 Reservation with code 'RSV-CANONICAL-DIFF-999'.
     * Create LegacyBookingMapping linking legacy_booking_id to reservation_id.
     * Verify report counts 1 booking and 1x revenue (no double counting).
     */
    public function test_report_deduplicates_via_legacy_booking_mapping_when_codes_differ(): void
    {
        $v1Booking = Booking::create([
            'court_id' => $this->courtAlpha->id,
            'user_id' => $this->customer->id,
            'code' => 'BK-LEGACY-DIFF-001',
            'status' => 'confirmed',
            'total_amount' => 350000,
        ]);

        $v2Reservation = Reservation::create([
            'organization_id' => $this->orgAlpha->id,
            'court_id' => $this->courtAlpha->id,
            'user_id' => $this->customer->id,
            'code' => 'RSV-CANONICAL-DIFF-999',
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 350000,
            'confirmed_at' => now(),
            'metadata' => [],
        ]);

        LegacyBookingMapping::create([
            'organization_id' => $this->orgAlpha->id,
            'legacy_booking_id' => $v1Booking->id,
            'reservation_id' => $v2Reservation->id,
            'legacy_code' => $v1Booking->code,
            'legacy_status' => 'confirmed',
            'legacy_total_amount' => 350000,
            'legacy_slot_count' => 1,
            'source_checksum' => hash('sha256', 'diff-codes'),
            'migrated_at' => now(),
        ]);

        $response = $this->actingAs($this->ownerAlpha)
            ->getJson('/api/v1/admin/reports?from='.now()->startOfMonth()->toDateString().'&to='.now()->endOfMonth()->toDateString());

        $response->assertOk();
        $response->assertJsonPath('bookings', 1);
        $response->assertJsonPath('confirmed', 1);
        $response->assertJsonPath('booking_revenue', 350000);
        $response->assertJsonPath('owner_revenue', 350000);
        $response->assertJsonPath('revenue', 350000);
    }

    /**
     * Test 2: Combines POS orders and owner refunds with net calculation.
     * Add confirmed booking (300k), POS order (150k), and owner refund (50k).
     * Verify owner_revenue = 450k, net_owner_revenue = 400k.
     */
    public function test_report_combines_pos_orders_and_owner_refunds_with_net_calculation(): void
    {
        // 1. Confirmed booking: 300k
        $this->mappedBooking('BK-NET-300', 300000);

        // 2. POS order: 150k
        PosOrder::create([
            'organization_id' => $this->orgAlpha->id,
            'venue_id' => $this->venueAlpha->id,
            'cashier_id' => $this->ownerAlpha->id,
            'customer_id' => $this->customer->id,
            'status' => 'completed',
            'payment_method' => 'cash',
            'subtotal' => 150000,
            'total_amount' => 150000,
            'completed_at' => now(),
        ]);

        // 3. Owner refund: 50k
        OwnerRefundObligation::create([
            'organization_id' => $this->orgAlpha->id,
            'venue_id' => $this->venueAlpha->id,
            'payer_id' => $this->customer->id,
            'reference_code' => 'REF-NET-050',
            'idempotency_key' => 'IDEM-REF-NET-'.uniqid(),
            'request_fingerprint' => hash('sha256', uniqid()),
            'slot_ids' => [1],
            'gross_amount' => 50000,
            'fee_amount' => 0,
            'amount' => 50000,
            'currency' => 'VND',
            'status' => 'approved',
            'reason' => 'Customer schedule change refund',
            'policy_snapshot' => ['type' => 'flexible'],
            'recipient_ciphertext' => Crypt::encryptString(json_encode(['name' => 'Customer'])),
            'due_at' => now()->addDays(2),
            'requested_at' => now(),
            'requested_by' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->ownerAlpha)
            ->getJson('/api/v1/admin/reports?venue_id='.$this->venueAlpha->id);

        $response->assertOk();
        $response->assertJsonPath('booking_revenue', 300000);
        $response->assertJsonPath('pos_revenue', 150000);
        $response->assertJsonPath('owner_revenue', 450000);
        $response->assertJsonPath('owner_refunds', 50000);
        $response->assertJsonPath('net_owner_revenue', 400000);
        $response->assertJsonPath('revenue', 450000);
    }

    /**
     * Test 3: Keeps platform subscriptions separate from owner revenue.
     * Add platform subscription fee (600k).
     * Verify owner revenue does not include platform fee, and owner cannot see platform fees.
     */
    public function test_report_keeps_platform_subscriptions_separate_from_owner_revenue(): void
    {
        // Owner booking: 300k
        $this->mappedBooking('BK-SEP-300', 300000);

        // Platform subscription attempt: 600k
        PlatformPaymentAttempt::create([
            'organization_id' => $this->orgAlpha->id,
            'reference_code' => 'SUB-FEE-600',
            'amount' => 600000,
            'currency' => 'VND',
            'provider' => 'vnpay',
            'status' => 'succeeded',
            'paid_at' => now(),
        ]);

        // 1. Venue owner inspects reports: platform_fees is 0, owner_revenue does NOT include 600k
        $ownerResponse = $this->actingAs($this->ownerAlpha)
            ->getJson('/api/v1/admin/reports?venue_id='.$this->venueAlpha->id);

        $ownerResponse->assertOk();
        $ownerResponse->assertJsonPath('owner_revenue', 300000);
        $ownerResponse->assertJsonPath('booking_revenue', 300000);
        $ownerResponse->assertJsonPath('platform_fees', 0);
        $ownerResponse->assertJsonPath('net_owner_revenue', 300000);

        // 2. Platform admin inspects reports: platform_fees is 600k, owner_revenue remains 300k
        $adminResponse = $this->actingAs($this->platformAdmin)
            ->getJson('/api/v1/admin/reports?venue_id='.$this->venueAlpha->id);

        $adminResponse->assertOk();
        $adminResponse->assertJsonPath('owner_revenue', 300000);
        $adminResponse->assertJsonPath('booking_revenue', 300000);
        $adminResponse->assertJsonPath('platform_fees', 600000);
        $adminResponse->assertJsonPath('net_owner_revenue', 300000);
    }

    /**
     * Test 4: Enforces venue scoping and date filtering.
     * Verify non-platform admin cannot access unauthorized venue_id (403), and events outside date range are excluded.
     */
    public function test_report_enforces_venue_scoping_and_date_filtering(): void
    {
        // 1. Non-platform admin tries to query unauthorized venue Beta -> 403 Forbidden
        $this->actingAs($this->ownerAlpha)
            ->getJson('/api/v1/admin/reports?venue_id='.$this->venueBeta->id)
            ->assertStatus(403);

        $this->actingAs($this->ownerAlpha)
            ->getJson('/api/v1/admin/reports/csv?venue_id='.$this->venueBeta->id)
            ->assertStatus(403);

        // 2. Events inside vs outside date range
        $currentFrom = now()->startOfMonth()->toDateString();
        $currentTo = now()->endOfMonth()->toDateString();

        // Inside current range: 200k
        $this->mappedBooking('BK-IN-RANGE', 200000);

        // Outside date range (3 months ago): 500k
        $oldReservation = Reservation::create([
            'organization_id' => $this->orgAlpha->id,
            'court_id' => $this->courtAlpha->id,
            'user_id' => $this->customer->id,
            'code' => 'RSV-OLD-OUT',
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 500000,
        ]);
        $oldReservation->forceFill(['created_at' => now()->subMonths(3)])->save();

        $response = $this->actingAs($this->ownerAlpha)
            ->getJson('/api/v1/admin/reports?venue_id='.$this->venueAlpha->id.'&from='.$currentFrom.'&to='.$currentTo);

        $response->assertOk();
        $response->assertJsonPath('bookings', 1);
        $response->assertJsonPath('confirmed', 1);
        $response->assertJsonPath('owner_revenue', 200000);
        $response->assertJsonPath('booking_revenue', 200000);
    }

    /**
     * Test 5: CSV export streams all canonical sources with receiver attribution.
     * Test /api/admin/reports/csv (or admin route). Verify CSV headers, rows for booking, pos, refund, and net amounts.
     */
    public function test_csv_export_streams_all_canonical_sources_with_receiver_attribution(): void
    {
        // 1. Mapped Booking & Reservation (deduplicated to 1 row, 300k)
        $code = 'BK-STREAM-300';
        $v1 = Booking::create([
            'court_id' => $this->courtAlpha->id,
            'user_id' => $this->customer->id,
            'code' => $code,
            'status' => 'confirmed',
            'total_amount' => 300000,
        ]);
        Reservation::create([
            'organization_id' => $this->orgAlpha->id,
            'court_id' => $this->courtAlpha->id,
            'user_id' => $this->customer->id,
            'code' => $code,
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 300000,
            'metadata' => ['booking_id' => $v1->id],
        ]);

        // 2. POS Order (150k)
        PosOrder::create([
            'organization_id' => $this->orgAlpha->id,
            'venue_id' => $this->venueAlpha->id,
            'cashier_id' => $this->ownerAlpha->id,
            'customer_id' => $this->customer->id,
            'status' => 'completed',
            'payment_method' => 'cash',
            'subtotal' => 150000,
            'total_amount' => 150000,
            'payment_reference' => 'POS-STREAM-150',
            'completed_at' => now(),
        ]);

        // 3. Owner Refund (50k)
        OwnerRefundObligation::create([
            'organization_id' => $this->orgAlpha->id,
            'venue_id' => $this->venueAlpha->id,
            'payer_id' => $this->customer->id,
            'reference_code' => 'REF-STREAM-050',
            'idempotency_key' => 'IDEM-STREAM-'.uniqid(),
            'request_fingerprint' => hash('sha256', uniqid()),
            'slot_ids' => [1],
            'gross_amount' => 50000,
            'fee_amount' => 0,
            'amount' => 50000,
            'currency' => 'VND',
            'status' => 'approved',
            'reason' => 'Stream refund test',
            'policy_snapshot' => ['type' => 'flexible'],
            'recipient_ciphertext' => Crypt::encryptString(json_encode(['name' => 'Customer'])),
            'due_at' => now()->addDays(2),
            'requested_at' => now(),
            'requested_by' => $this->customer->id,
        ]);

        // 4. Platform Subscription (600k)
        PlatformPaymentAttempt::create([
            'organization_id' => $this->orgAlpha->id,
            'reference_code' => 'SUB-STREAM-600',
            'amount' => 600000,
            'currency' => 'VND',
            'provider' => 'vnpay',
            'status' => 'succeeded',
            'paid_at' => now(),
        ]);

        // Test platform admin CSV download
        $response = $this->actingAs($this->platformAdmin)
            ->get('/api/admin/reports/csv?venue_id='.$this->venueAlpha->id);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $content = $response->streamedContent();
        $lines = array_filter(explode("\n", trim($content)));

        // Header check
        $header = str_getcsv(array_shift($lines));
        $expectedHeader = ['code', 'source_type', 'customer', 'venue_or_court', 'receiver_type', 'status', 'gross_amount', 'refund_amount', 'net_amount', 'created_at'];
        $this->assertSame($expectedHeader, $header);

        // Parse rows
        $rows = array_map('str_getcsv', $lines);

        // Must have exactly 4 rows: 1 booking (deduplicated), 1 pos_order, 1 owner_refund, 1 platform_subscription
        $this->assertCount(4, $rows);

        $bookingRow = collect($rows)->firstWhere(1, 'booking');
        $this->assertNotNull($bookingRow);
        $this->assertSame('BK-STREAM-300', $bookingRow[0]);
        $this->assertSame('owner', $bookingRow[4]);
        $this->assertSame('confirmed', $bookingRow[5]);
        $this->assertSame('300000', $bookingRow[6]); // gross_amount
        $this->assertSame('0', $bookingRow[7]);      // refund_amount
        $this->assertSame('300000', $bookingRow[8]); // net_amount

        $posRow = collect($rows)->firstWhere(1, 'pos_order');
        $this->assertNotNull($posRow);
        $this->assertSame('POS-STREAM-150', $posRow[0]);
        $this->assertSame('owner', $posRow[4]);
        $this->assertSame('completed', $posRow[5]);
        $this->assertSame('150000', $posRow[6]); // gross_amount
        $this->assertSame('0', $posRow[7]);      // refund_amount
        $this->assertSame('150000', $posRow[8]); // net_amount

        $refundRow = collect($rows)->firstWhere(1, 'owner_refund');
        $this->assertNotNull($refundRow);
        $this->assertSame('REF-STREAM-050', $refundRow[0]);
        $this->assertSame('owner', $refundRow[4]);
        $this->assertSame('0', $refundRow[6]);       // gross_amount
        $this->assertSame('50000', $refundRow[7]);   // refund_amount
        $this->assertSame('-50000', $refundRow[8]);  // net_amount

        $subRow = collect($rows)->firstWhere(1, 'platform_subscription');
        $this->assertNotNull($subRow);
        $this->assertSame('SUB-STREAM-600', $subRow[0]);
        $this->assertSame('platform', $subRow[4]);
        $this->assertSame('succeeded', $subRow[5]);
        $this->assertSame('600000', $subRow[6]); // gross_amount
        $this->assertSame('0', $subRow[7]);      // refund_amount
        $this->assertSame('600000', $subRow[8]); // net_amount

        // Calculate owner net total from CSV: 300,000 + 150,000 - 50,000 = 400,000
        $ownerNetSum = collect($rows)->where(4, 'owner')->sum(fn ($r) => (int) $r[8]);
        $this->assertSame(400000, $ownerNetSum);

        // Also test as Venue Owner: should NOT contain platform_subscription row
        $ownerExport = $this->actingAs($this->ownerAlpha)
            ->get('/api/v1/admin/reports.csv?venue_id='.$this->venueAlpha->id);

        $ownerExport->assertOk();
        $ownerLines = array_filter(explode("\n", trim($ownerExport->streamedContent())));
        array_shift($ownerLines); // header
        $ownerRows = array_map('str_getcsv', $ownerLines);

        $this->assertCount(3, $ownerRows);
        $this->assertNull(collect($ownerRows)->firstWhere(1, 'platform_subscription'));
    }

    /**
     * A 'legacy' mirror is counted by its mapping status, any other mirror by its own: a V1 no-show keeps its
     * mirror confirmed, as a V2 no-show does. Mirrors are shown with their V1 code (display rule).
     */
    public function test_report_uses_mapping_status_and_v1_code_for_mapped_mirrors(): void
    {
        $this->mappedBooking('BK-MIRROR-OK', 250000);
        $noShow = $this->mappedBooking('BK-MIRROR-NOSHOW', 300000);
        $noShow->forceFill(['status' => 'no_show'])->save();
        app(LegacyBookingBackfillService::class)->syncProjection($noShow->fresh('slots'));
        $this->mappedBooking('BK-MIRROR-GONE', 400000, 'cancelled');
        $this->assertSame('legacy', Reservation::withoutGlobalScope('tenant_isolation')->where('code', 'LEGACY-BK-MIRROR-GONE')->value('status'));

        $this->actingAs($this->ownerAlpha)
            ->getJson('/api/v1/admin/reports?venue_id='.$this->venueAlpha->id)
            ->assertOk()
            ->assertJsonPath('bookings', 2)
            ->assertJsonPath('booking_revenue', 550000);

        $csv = $this->actingAs($this->ownerAlpha)
            ->get('/api/v1/admin/reports.csv?venue_id='.$this->venueAlpha->id)
            ->assertOk()
            ->streamedContent();
        $rows = array_map('str_getcsv', array_slice(array_filter(explode("\n", trim($csv))), 1));

        $actual = array_map(fn ($row) => [$row[0], $row[1], $row[5], $row[6]], $rows);
        sort($actual);
        $this->assertSame([['BK-MIRROR-NOSHOW', 'booking', 'confirmed', '300000'], ['BK-MIRROR-OK', 'booking', 'confirmed', '250000']], $actual);
    }

    /** A V1 booking as it exists after the backfill gate: one slot and a reservation mirror. */
    private function mappedBooking(string $code, int $amount, string $status = 'confirmed'): Booking
    {
        $booking = Booking::create([
            'court_id' => $this->courtAlpha->id,
            'user_id' => $this->customer->id,
            'code' => $code,
            'status' => $status,
            'total_amount' => $amount,
        ]);
        $start = now()->addDays(Booking::count())->setTime(10, 0)->utc();
        $booking->slots()->create(['court_id' => $this->courtAlpha->id, 'starts_at' => $start, 'ends_at' => $start->copy()->addHour(), 'unit_price' => $amount, 'status' => 'confirmed']);
        app(LegacyBookingBackfillService::class)->backfillBooking($booking);

        return $booking;
    }
}
