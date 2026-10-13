<?php

namespace Tests\Feature\Readiness;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\CashShift;
use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Payment;
use App\Models\PosOrder;
use App\Models\PosProduct;
use App\Models\User;
use App\Models\Venue;
use App\Models\WalletTransaction;
use App\Services\Payment\WalletPaymentService;
use App\Services\Reservation\LegacyBookingBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * retire-v1-booking task 6.8: the removed V1 booking API and the old /api/v1 and /api/v2 prefixes answer 404 without writing anything,
 * a legacy booking stays readable through its reservation mirror, and SePay webhooks still record payments once.
 */
class RetiredV1BookingRoutesTest extends TestCase
{
    use RefreshDatabase;

    private const WATCHED_TABLES = ['bookings', 'booking_slots', 'payments', 'reservations', 'reservation_slots', 'price_quotes', 'idempotency_records', 'notifications'];

    public function test_every_removed_v1_booking_route_is_404_and_writes_nothing(): void
    {
        [$organization, $owner, $customer, $court] = $this->tenant();
        $booking = $this->legacyBooking($customer, $court);
        Payment::create(['booking_id' => $booking->id, 'external_transaction_id' => 'tx-legacy-paid', 'amount' => 150000, 'transfer_code' => $booking->code, 'status' => 'paid', 'received_at' => now()->subDays(3)]);
        $platformAdmin = User::factory()->create(['role' => 'platform_admin']);
        $code = $booking->code;
        $v1Routes = [
            ['GET', '/api/bookings'],
            ['POST', '/api/bookings'],
            ['POST', '/api/bookings/recurring'],
            ['GET', "/api/bookings/{$code}"],
            ['POST', "/api/bookings/{$code}/sync-v2"],
            ['GET', "/api/legacy/reservations/LEGACY-{$code}"],
            ['GET', "/api/bookings/{$code}/payment-status"],
            ['GET', "/api/bookings/{$code}/payment"],
            ['POST', "/api/bookings/{$code}/payment"],
            ['GET', "/api/bookings/{$code}/refund-quote"],
            ['GET', "/api/bookings/{$code}/refund-requests"],
            ['POST', "/api/bookings/{$code}/refund-requests"],
            ['POST', "/api/bookings/{$code}/reschedule-preview"],
            ['POST', "/api/bookings/{$code}/reschedule-requests"],
            ['POST', "/api/bookings/{$code}/coupon"],
            ['GET', "/api/bookings/{$code}/calendar.ics"],
        ];
        // The V1 no-show was a PATCH; POST is probed as well.
        $adminRoutes = [
            ['GET', '/api/admin/bookings'],
            ['PATCH', "/api/admin/bookings/{$booking->id}/no-show"],
            ['POST', "/api/admin/bookings/{$booking->id}/no-show"],
        ];
        $payload = [
            'court_id' => $court->id,
            'slots' => [now()->addDays(3)->setTime(10, 0)->toIso8601String()],
            'coupon_code' => 'SAVE20K',
            'reason' => 'retired route probe',
        ];
        $callers = [
            'customer' => [$customer, [...$v1Routes, ...$adminRoutes]],
            'venue admin' => [$owner, $adminRoutes],
            'platform admin' => [$platformAdmin, $adminRoutes],
        ];

        foreach ($callers as $role => [$caller, $routes]) {
            foreach ($routes as [$method, $uri]) {
                $before = $this->snapshot();

                $this->actingAs($caller)->withHeaders([
                    'X-Organization-ID' => (string) $organization->id,
                    'Idempotency-Key' => "retired {$role} {$method} {$uri}",
                ])->json($method, $uri, $payload)->assertNotFound();

                $this->assertSame($before, $this->snapshot(), "{$method} {$uri} as {$role} changed a watched table.");
            }
        }
    }

    public function test_versioned_api_paths_are_not_found_and_write_nothing(): void
    {
        [$organization, , $customer, $court] = $this->tenant();
        $slots = ['court_id' => $court->id, 'slots' => [now()->addDays(3)->setTime(10, 0)->toIso8601String()]];
        $probes = [
            ['GET', '/api/v1/courts', []],
            ['POST', '/api/v1/bookings', $slots],
            ['GET', '/api/v2/me/reservations', []],
            ['POST', '/api/v2/price-quotes', $slots],
            ['POST', '/api/v2/reservations', $slots],
            ['POST', '/api/v1/webhooks/sepay', ['id' => 'tx-stale-client', 'content' => 'POS STALE', 'transferAmount' => 40000, 'transferType' => 'in']],
            ['GET', '/api/v1/auth/google/callback', []],
        ];

        foreach ($probes as [$method, $uri, $payload]) {
            $before = $this->snapshot();

            $this->actingAs($customer)->withHeaders([
                'X-Organization-ID' => (string) $organization->id,
                'Idempotency-Key' => "stale client {$method} {$uri}",
            ])->json($method, $uri, $payload)->assertNotFound();

            $this->assertSame($before, $this->snapshot(), "{$method} {$uri} changed a watched table.");
        }
    }

    public function test_a_mapped_legacy_booking_reads_through_reservations_with_its_original_code_amount_and_status(): void
    {
        [$organization, $owner, $customer, $court] = $this->tenant();
        $booking = $this->legacyBooking($customer, $court);
        $reservationId = (int) $booking->legacyMapping()->value('reservation_id');

        $this->actingAs($customer)->withHeader('X-Organization-ID', (string) $organization->id)
            ->getJson("/api/reservations/{$reservationId}")
            ->assertOk()
            ->assertJsonPath('reservation.code', 'LEGACY-BK-RETIRED-001')
            ->assertJsonPath('reservation.total_amount', 150000)
            ->assertJsonPath('reservation.status', 'legacy')
            ->assertJsonPath('reservation.legacy_mapping.0.legacy_code', 'BK-RETIRED-001')
            ->assertJsonPath('reservation.legacy_mapping.0.legacy_status', 'completed');

        $listed = collect($this->actingAs($owner)->getJson('/api/admin/reservations')->assertOk()->json('data'))->firstWhere('id', $reservationId);
        $this->assertSame('LEGACY-BK-RETIRED-001', $listed['code']);
        $this->assertSame(150000, $listed['total_amount']);
        $this->assertSame('BK-RETIRED-001', $listed['legacy_mapping'][0]['legacy_code']);
        $this->assertSame('completed', $listed['legacy_mapping'][0]['legacy_status']);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'code' => 'BK-RETIRED-001', 'total_amount' => 150000, 'status' => 'completed']);
    }

    public function test_sepay_webhook_settles_a_pos_qr_order_once(): void
    {
        [$organization] = $this->tenant();
        config(['features.flags.tenant_pos_and_wallet' => true, 'features.pilot_organizations' => [(string) $organization->id]]);
        $cashier = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $cashier->id, 'role' => OrganizationMember::ROLE_CASHIER, 'status' => 'active']);
        CashShift::create(['organization_id' => $organization->id, 'cashier_id' => $cashier->id, 'opening_cash' => 0, 'opened_at' => now(), 'status' => 'open']);
        $product = PosProduct::create(['organization_id' => $organization->id, 'sku' => 'WATER-500', 'name' => 'Water 500ml', 'price' => 20000, 'cost' => 10000, 'stock_quantity' => 10, 'is_active' => true]);
        $order = PosOrder::findOrFail($this->actingAs($cashier)->postJson('/api/pos/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => 'qr',
        ], [
            'X-Organization-Id' => $organization->id,
            'Idempotency-Key' => 'retired-v1-pos-qr',
        ])->assertCreated()->json('order.id'));
        $this->assertSame('pending_payment', $order->status);

        $webhook = ['id' => 'tx-sepay-pos-1', 'content' => "POS {$order->payment_reference}", 'transferAmount' => 40000, 'transferType' => 'in'];
        $this->deliverSepay($webhook);
        $this->deliverSepay($webhook);

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame(1, Payment::where('external_transaction_id', 'tx-sepay-pos-1')->count());
        $this->assertDatabaseHas('payments', ['external_transaction_id' => 'tx-sepay-pos-1', 'amount' => 40000, 'status' => 'paid']);
        $this->assertSame(1, DB::table('journals')->where('event_type', 'pos.sale')->where('event_id', "pos:{$order->id}")->count());
    }

    public function test_sepay_webhook_credits_a_wallet_deposit_once(): void
    {
        [$organization, , $customer] = $this->tenant();
        $deposit = $this->actingAs($customer)->postJson('/api/wallet/deposits', [
            'amount' => 250000,
            'organization_id' => $organization->id,
            'provider' => 'sepay',
        ])->assertCreated()->json();

        $webhook = ['id' => 'tx-sepay-deposit-1', 'code' => $deposit['transfer_info']['code'], 'transferAmount' => 250000, 'transferType' => 'in'];
        $this->deliverSepay($webhook);
        $this->deliverSepay($webhook);

        $wallet = app(WalletPaymentService::class)->getOrCreateWallet($customer->id, $organization->id);
        $this->assertSame(250000, (int) $wallet->balance);
        $this->assertSame(1, WalletTransaction::where('wallet_account_id', $wallet->id)->where('source_type', 'wallet_deposit')->count());
        $this->assertSame(1, Payment::where('external_transaction_id', 'tx-sepay-deposit-1')->count());
        $this->assertDatabaseHas('payments', ['external_transaction_id' => 'tx-sepay-deposit-1', 'amount' => 250000, 'status' => 'paid']);
        $this->assertDatabaseHas('payment_intents', ['id' => $deposit['id'], 'status' => 'succeeded']);
    }

    /** @return array{0: Organization, 1: User, 2: User, 3: Court} */
    private function tenant(): array
    {
        $organization = Organization::create(['name' => 'Retired V1 Club', 'slug' => 'retired-v1-'.uniqid(), 'status' => 'active', 'partner_status' => 'approved']);
        $owner = User::factory()->create();
        $customer = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER, 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $customer->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);
        $venue = Venue::create(['organization_id' => $organization->id, 'name' => 'Retired V1 Venue', 'slug' => 'retired-v1-venue-'.uniqid(), 'status' => 'published', 'is_active' => true]);

        return [$organization, $owner, $customer, Court::create(['venue_id' => $venue->id, 'name' => 'Retired V1 Court', 'is_active' => true])];
    }

    private function legacyBooking(User $customer, Court $court): Booking
    {
        $booking = Booking::create(['code' => 'BK-RETIRED-001', 'user_id' => $customer->id, 'court_id' => $court->id, 'total_amount' => 150000, 'status' => 'completed']);
        BookingSlot::create([
            'booking_id' => $booking->id,
            'court_id' => $court->id,
            'starts_at' => now()->subDays(3)->setTime(18, 0),
            'ends_at' => now()->subDays(3)->setTime(19, 0),
            'unit_price' => 150000,
            'status' => 'confirmed',
        ]);
        app(LegacyBookingBackfillService::class)->backfillBooking($booking);

        return $booking;
    }

    private function deliverSepay(array $payload): void
    {
        config(['services.sepay.hmac_secret' => 'retired-v1-sepay-secret']);
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/webhooks/sepay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SEPAY_SIGNATURE' => hash_hmac('sha256', $raw, 'retired-v1-sepay-secret'),
        ], $raw)->assertOk()->assertJson(['success' => true]);
    }

    /** @return array<string, array<string, mixed>> */
    private function snapshot(): array
    {
        return collect(self::WATCHED_TABLES)->mapWithKeys(fn (string $table): array => [
            $table => (array) DB::selectOne("select count(*) as total, md5(coalesce(string_agg(t::text, '|' order by t::text), '')) as checksum from {$table} t"),
        ])->all();
    }
}
