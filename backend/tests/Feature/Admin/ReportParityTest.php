<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\LegacyBookingMapping;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerRefundObligation;
use App\Models\PlatformPaymentAttempt;
use App\Models\PosOrder;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\OwnerRefundService;
use App\Services\Payment\WalletPaymentService;
use App\Services\Reservation\LegacyBookingBackfillService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Literal report numbers on one fixed dataset. Written against the bookings-based report and kept
 * unchanged when the report moved to reservations (retire-v1-booking task 2.4): the numbers must not move.
 */
class ReportParityTest extends TestCase
{
    use RefreshDatabase;

    private const RANGE = 'from=2026-03-01&to=2026-03-31';

    private const CREATED_AT = '2026-03-02T08:00:00+07:00';

    private User $platformAdmin;

    private User $ownerA;

    private User $customer;

    private Venue $venueA;

    private Venue $venueB;

    public function test_report_and_csv_numbers_on_the_fixed_dataset(): void
    {
        $this->seedDataset();

        $this->assertReport($this->actingAs($this->ownerA)->getJson('/api/admin/reports?'.self::RANGE), [
            'bookings' => 3, 'confirmed' => 3, 'revenue' => 580000, 'owner_revenue' => 580000, 'booking_revenue' => 530000,
            'pos_revenue' => 50000, 'owner_refunds' => 90000, 'net_owner_revenue' => 490000, 'platform_fees' => 0,
            'pendingRefunds' => 70000, 'occupancyRate' => 4.8,
        ], [['hour' => '10:00', 'bookings' => 4], ['hour' => '11:00', 'bookings' => 2]], [
            ['name' => 'A1', 'bookings' => 2, 'revenue' => 380000],
            ['name' => 'A2', 'bookings' => 1, 'revenue' => 150000],
        ]);

        $this->assertReport($this->actingAs($this->platformAdmin)->getJson('/api/admin/reports?'.self::RANGE), [
            'bookings' => 5, 'confirmed' => 5, 'revenue' => 980000, 'owner_revenue' => 980000, 'booking_revenue' => 930000,
            'pos_revenue' => 50000, 'owner_refunds' => 140000, 'net_owner_revenue' => 840000, 'platform_fees' => 600000,
            'pendingRefunds' => 90000, 'occupancyRate' => 5.8,
        ], [['hour' => '10:00', 'bookings' => 6], ['hour' => '11:00', 'bookings' => 3]], [
            ['name' => 'A1', 'bookings' => 2, 'revenue' => 380000],
            ['name' => 'A2', 'bookings' => 1, 'revenue' => 150000],
            ['name' => 'B1', 'bookings' => 2, 'revenue' => 400000],
        ]);

        $this->assertReport($this->actingAs($this->platformAdmin)->getJson('/api/admin/reports?'.self::RANGE.'&venue_id='.$this->venueB->id), [
            'bookings' => 2, 'confirmed' => 2, 'revenue' => 400000, 'owner_revenue' => 400000, 'booking_revenue' => 400000,
            'pos_revenue' => 0, 'owner_refunds' => 50000, 'net_owner_revenue' => 350000, 'platform_fees' => 0,
            'pendingRefunds' => 20000, 'occupancyRate' => 9.7,
        ], [['hour' => '10:00', 'bookings' => 2], ['hour' => '11:00', 'bookings' => 1]], [
            ['name' => 'B1', 'bookings' => 2, 'revenue' => 400000],
        ]);

        $this->assertCsv($this->actingAs($this->ownerA)->get('/api/admin/reports.csv?'.self::RANGE), [
            ['booking', 'customer@parity.local', 'A1', 'owner', 'confirmed', '180000', '0', '180000'],
            ['booking', 'customer@parity.local', 'A1', 'owner', 'confirmed', '200000', '0', '200000'],
            ['booking', 'customer@parity.local', 'A2', 'owner', 'confirmed', '150000', '0', '150000'],
            ['owner_refund', 'customer@parity.local', 'Venue A', 'owner', 'approved', '0', '90000', '-90000'],
            ['pos_order', 'customer@parity.local', 'Venue A', 'owner', 'completed', '50000', '0', '50000'],
        ]);

        $this->assertCsv($this->actingAs($this->platformAdmin)->get('/api/admin/reports/csv?'.self::RANGE), [
            ['booking', 'customer@parity.local', 'A1', 'owner', 'confirmed', '180000', '0', '180000'],
            ['booking', 'customer@parity.local', 'A1', 'owner', 'confirmed', '200000', '0', '200000'],
            ['booking', 'customer@parity.local', 'A2', 'owner', 'confirmed', '150000', '0', '150000'],
            ['booking', 'customer@parity.local', 'B1', 'owner', 'confirmed', '200000', '0', '200000'],
            ['booking', 'customer@parity.local', 'B1', 'owner', 'confirmed', '200000', '0', '200000'],
            ['owner_refund', 'customer@parity.local', 'Venue A', 'owner', 'approved', '0', '90000', '-90000'],
            ['owner_refund', 'customer@parity.local', 'Venue B', 'owner', 'acknowledged', '0', '50000', '-50000'],
            ['platform_subscription', 'Org A', 'Platform', 'platform', 'succeeded', '600000', '0', '600000'],
            ['pos_order', 'customer@parity.local', 'Venue A', 'owner', 'completed', '50000', '0', '50000'],
        ]);
    }

    private function seedDataset(): void
    {
        $this->travelTo(Carbon::parse(self::CREATED_AT));

        $this->platformAdmin = User::factory()->create(['email' => 'admin@parity.local', 'role' => 'admin', 'email_verified_at' => now()]);
        $this->ownerA = User::factory()->create(['email' => 'owner-a@parity.local', 'role' => 'customer', 'email_verified_at' => now(), 'phone_verified_at' => now()]);
        $this->customer = User::factory()->create(['email' => 'customer@parity.local', 'role' => 'customer', 'email_verified_at' => now()]);

        [$orgA, $this->venueA, $a1, $a2] = $this->tenant('Org A', 'Venue A', ['A1', 'A2'], [100000, 150000], ['10:00', '11:00']);
        [$orgB, $this->venueB, $b1] = $this->tenant('Org B', 'Venue B', ['B1'], [200000], ['10:00']);
        OrganizationMember::create(['organization_id' => $orgA->id, 'user_id' => $this->ownerA->id, 'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER, 'status' => 'active']);

        // V1 bookings as checkoutV1 wrote them (legacy-wallet cohort), each backfilled to a mirror reservation.
        $v1Confirmed = $this->checkoutV1($a1, ['2026-03-10 10:00', '2026-03-10 11:00']);
        $v1NoShow = $this->checkoutV1($a2, ['2026-03-10 10:00']);
        $v1Cancelled = $this->checkoutV1($a1, ['2026-03-11 10:00']);
        $v1VenueB = $this->checkoutV1($b1, ['2026-03-10 10:00']);

        // Historical V1 rows already completed / no-show when the backfill mapped them (mirror status 'legacy').
        $this->historicalBooking($a2, 'completed', '2026-03-12 10:00', 150000);
        $this->historicalBooking($b1, 'no_show', '2026-03-12 11:00', 200000);

        // V2-native reservations.
        $rsvA = $this->nativeReservation($orgA, $a1, 'confirmed', [['2026-03-13 10:00', 90000], ['2026-03-13 11:00', 90000]]);
        $this->nativeReservation($orgA, $a2, 'cancelled', [['2026-03-13 10:00', 150000]]);
        $this->nativeReservation($orgA, $a2, 'held', [['2026-03-14 10:00', 150000]]);
        $rsvB = $this->nativeReservation($orgB, $b1, 'confirmed', [['2026-03-13 10:00', 200000]]);

        // V1 cancellation through an approved owner refund: the mirror and its slots cancel, the frozen booking row stays.
        $mirror = $this->mirrorOf($v1Cancelled);
        $approved = $this->ownerRefund($mirror, 'requested', 100000, 90000, 'REF-PARITY-APPROVED', $v1Cancelled->id);
        app(OwnerRefundService::class)->approve($approved, $this->ownerA);
        $this->assertSame('cancelled', $mirror->fresh()->status);
        $this->ownerRefund($rsvA, 'requested', 40000, 40000, 'REF-PARITY-REQUESTED');
        $this->ownerRefund($rsvB, 'acknowledged', 50000, 50000, 'REF-PARITY-ACK');

        Refund::create(['booking_id' => $v1Confirmed->id, 'amount' => 30000, 'status' => 'pending']);
        Refund::create(['booking_id' => $v1VenueB->id, 'amount' => 20000, 'status' => 'pending']);

        PosOrder::create([
            'organization_id' => $orgA->id, 'venue_id' => $this->venueA->id, 'cashier_id' => $this->ownerA->id, 'customer_id' => $this->customer->id,
            'status' => 'completed', 'payment_method' => 'cash', 'subtotal' => 50000, 'total_amount' => 50000,
            'payment_reference' => 'POS-PARITY-050', 'completed_at' => now(),
        ]);
        PlatformPaymentAttempt::create([
            'organization_id' => $orgA->id, 'reference_code' => 'SUB-PARITY-600', 'amount' => 600000,
            'currency' => 'VND', 'provider' => 'vnpay', 'status' => 'succeeded', 'paid_at' => now(),
        ]);

        // A V1 admin no-show made before Phase C: legacy_status followed, the mirror stayed confirmed as a V2 no-show does (I3, I14).
        $v1NoShow->update(['status' => 'no_show']);
        LegacyBookingMapping::withoutGlobalScope('tenant_isolation')->where('legacy_booking_id', $v1NoShow->id)->update(['legacy_status' => 'no_show']);
        $this->assertSame('confirmed', $this->mirrorOf($v1NoShow)->status);
        $this->assertSame(6, LegacyBookingMapping::withoutGlobalScope('tenant_isolation')->count());
    }

    /** @return array<int, mixed> */
    private function tenant(string $orgName, string $venueName, array $courtNames, array $prices, array $hours): array
    {
        $org = Organization::create(['name' => $orgName, 'slug' => str($orgName)->slug().'-'.uniqid(), 'status' => 'active', 'partner_status' => 'approved']);
        $venue = Venue::create(['organization_id' => $org->id, 'name' => $venueName, 'slug' => str($venueName)->slug().'-'.uniqid(), 'is_active' => true]);
        $wallets = app(WalletPaymentService::class);
        $wallets->credit($wallets->getOrCreateWallet($this->customer->id, $org->id), 2000000, 'test_topup', 1, 'Parity fixture');

        $courts = [];
        foreach ($courtNames as $index => $name) {
            $court = Court::create(['venue_id' => $venue->id, 'name' => $name, 'is_active' => true]);
            foreach (range(1, 7) as $weekday) {
                foreach ($hours as $hour) {
                    CourtPriceSlot::create([
                        'court_id' => $court->id, 'weekday' => $weekday, 'starts_at' => $hour,
                        'ends_at' => Carbon::parse($hour)->addHour()->format('H:i'), 'price' => $prices[$index], 'is_active' => true,
                    ]);
                }
            }
            $courts[] = $court;
        }

        return [$org, $venue, ...$courts];
    }

    /** A confirmed legacy-wallet V1 booking as checkoutV1 wrote it, mirrored to its reservation. */
    private function checkoutV1(Court $court, array $localStarts): Booking
    {
        $price = (int) CourtPriceSlot::where('court_id', $court->id)->value('price');
        $booking = Booking::create([
            'court_id' => $court->id, 'user_id' => $this->customer->id, 'code' => 'BK'.strtoupper(Str::random(12)),
            'status' => 'confirmed', 'total_amount' => $price * count($localStarts), 'payment_model' => 'legacy_wallet_v1',
        ]);
        foreach ($localStarts as $localStart) {
            $start = Carbon::parse($localStart, 'Asia/Ho_Chi_Minh')->utc();
            $booking->slots()->create(['court_id' => $court->id, 'starts_at' => $start, 'ends_at' => $start->copy()->addHour(), 'unit_price' => $price, 'status' => 'confirmed']);
        }
        app(LegacyBookingBackfillService::class)->backfillBooking($booking);

        return $booking;
    }

    private function historicalBooking(Court $court, string $status, string $localStart, int $price): void
    {
        $booking = Booking::create([
            'court_id' => $court->id, 'user_id' => $this->customer->id, 'code' => 'BK-HIST-'.strtoupper($status),
            'status' => $status, 'total_amount' => $price, 'payment_model' => 'legacy_wallet_v1',
        ]);
        $start = Carbon::parse($localStart, 'Asia/Ho_Chi_Minh')->utc();
        $booking->slots()->create(['court_id' => $court->id, 'starts_at' => $start, 'ends_at' => $start->copy()->addHour(), 'unit_price' => $price, 'status' => 'confirmed']);
        app(LegacyBookingBackfillService::class)->backfillBooking($booking);
    }

    private function nativeReservation(Organization $org, Court $court, string $status, array $slots): Reservation
    {
        $reservation = Reservation::create([
            'organization_id' => $org->id, 'user_id' => $this->customer->id, 'court_id' => $court->id,
            'code' => 'RSV-PARITY-'.strtoupper(uniqid()), 'status' => $status, 'currency' => 'VND',
            'total_amount' => array_sum(array_column($slots, 1)), 'payment_model' => 'legacy_wallet_v1',
        ]);
        foreach ($slots as [$localStart, $price]) {
            $start = Carbon::parse($localStart, 'Asia/Ho_Chi_Minh')->utc();
            $reservation->slots()->create([
                'organization_id' => $org->id, 'court_id' => $court->id, 'starts_at' => $start,
                'ends_at' => $start->copy()->addHour(), 'unit_price' => $price, 'status' => $status,
            ]);
        }

        return $reservation;
    }

    private function mirrorOf(Booking $booking): Reservation
    {
        $mapping = LegacyBookingMapping::withoutGlobalScope('tenant_isolation')->where('legacy_booking_id', $booking->id)->firstOrFail();

        return Reservation::withoutGlobalScope('tenant_isolation')->with('slots')->findOrFail($mapping->reservation_id);
    }

    private function ownerRefund(Reservation $reservation, string $status, int $gross, int $amount, string $reference, ?int $bookingId = null): OwnerRefundObligation
    {
        return OwnerRefundObligation::create([
            'organization_id' => $reservation->organization_id, 'venue_id' => $reservation->court->venue_id,
            'reservation_id' => $reservation->id, 'booking_id' => $bookingId, 'payer_id' => $this->customer->id,
            'reference_code' => $reference, 'idempotency_key' => 'IDEM-'.$reference, 'request_fingerprint' => hash('sha256', $reference),
            'slot_ids' => $reservation->slots()->pluck('id')->all(), 'gross_amount' => $gross, 'fee_amount' => $gross - $amount,
            'amount' => $amount, 'currency' => 'VND', 'status' => $status, 'reason' => 'Parity fixture',
            'policy_snapshot' => ['type' => 'flexible'], 'recipient_ciphertext' => Crypt::encryptString('{"name":"Customer"}'),
            'due_at' => now()->addDays(2), 'requested_at' => now(), 'requested_by' => $this->customer->id,
        ]);
    }

    private function assertReport(TestResponse $response, array $scalars, array $byHour, array $byCourt): void
    {
        $response->assertOk();
        foreach ($scalars as $key => $value) {
            $this->assertSame($value, $response->json($key), $key);
        }
        $this->assertSame($byHour, collect($response->json('byHour'))->sortBy('hour')->values()->all());
        $this->assertSame($byCourt, collect($response->json('byCourt'))
            ->map(fn (array $row) => ['name' => $row['court']['name'], 'bookings' => $row['bookings'], 'revenue' => $row['revenue']])
            ->sortBy('name')->values()->all());
    }

    /** Rows without `code` (mirror codes follow the display rule) and with created_at checked separately. */
    private function assertCsv(TestResponse $response, array $expected): void
    {
        $response->assertOk();
        $lines = array_values(array_filter(explode("\n", trim($response->streamedContent()))));
        $this->assertSame('code,source_type,customer,venue_or_court,receiver_type,status,gross_amount,refund_amount,net_amount,created_at', array_shift($lines));
        $rows = array_map('str_getcsv', $lines);

        $this->assertSame(array_fill(0, count($expected), self::CREATED_AT), array_column($rows, 9));
        $actual = array_map(fn (array $row) => array_slice($row, 1, 8), $rows);
        sort($actual);
        sort($expected);
        $this->assertSame($expected, $actual);
    }
}
