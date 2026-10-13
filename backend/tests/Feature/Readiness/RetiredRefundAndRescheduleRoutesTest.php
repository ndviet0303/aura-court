<?php

namespace Tests\Feature\Readiness;

use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerRefundObligation;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * direct-owner-cutover 3.9: the removed refund, reschedule and refund-inbox routes answer 404 without writing anything,
 * and an old refund obligation stays stored unchanged as history.
 */
class RetiredRefundAndRescheduleRoutesTest extends TestCase
{
    use RefreshDatabase;

    private const WATCHED_TABLES = [
        'reservations', 'reservation_slots', 'reservation_entitlements', 'price_quotes', 'owner_payment_attempts',
        'owner_refund_obligations', 'owner_refund_allocations', 'owner_refund_evidence', 'refunds', 'idempotency_records', 'notifications',
    ];

    public function test_every_removed_refund_and_reschedule_route_is_404_and_writes_nothing(): void
    {
        $organization = Organization::create(['name' => 'Retired Refund Club', 'slug' => 'retired-refund-'.uniqid(), 'status' => 'active', 'partner_status' => 'approved']);
        $owner = User::factory()->create();
        $customer = User::factory()->create();
        $platformAdmin = User::factory()->create(['role' => 'platform_admin']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER, 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $customer->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);
        $venue = Venue::create(['organization_id' => $organization->id, 'name' => 'Retired Refund Venue', 'slug' => 'retired-refund-venue-'.uniqid(), 'status' => 'published', 'is_active' => true]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Retired Refund Court', 'is_active' => true]);
        $reservation = Reservation::create([
            'organization_id' => $organization->id, 'user_id' => $customer->id, 'court_id' => $court->id, 'code' => 'RES-RETIRED-REFUND',
            'status' => 'confirmed', 'currency' => 'VND', 'total_amount' => 200000, 'payment_model' => 'direct_owner_v1', 'confirmed_at' => now(),
        ]);
        $refund = OwnerRefundObligation::create([
            'organization_id' => $organization->id, 'venue_id' => $venue->id, 'reservation_id' => $reservation->id, 'payer_id' => $customer->id,
            'reference_code' => 'RF-HISTORY-1', 'idempotency_key' => 'history-1', 'request_fingerprint' => hash('sha256', 'history-1'),
            'slot_ids' => [], 'gross_amount' => 200000, 'fee_amount' => 0, 'amount' => 200000, 'currency' => 'VND',
            'status' => OwnerRefundObligation::STATUS_REQUESTED, 'reason' => 'Historical request', 'policy_snapshot' => [],
            'recipient_ciphertext' => Crypt::encryptString('{"name":"Customer"}'), 'due_at' => now()->addDay(), 'requested_at' => now(),
            'requested_by' => $customer->id,
        ]);
        $code = $reservation->code;
        $routes = [
            ['GET', "/api/reservations/{$code}/refund-quote"],
            ['GET', "/api/reservations/{$code}/refund-requests"],
            ['POST', "/api/reservations/{$code}/refund-requests"],
            ['POST', "/api/reservations/{$code}/reschedule-preview"],
            ['POST', "/api/reservations/{$code}/reschedule-requests"],
            ['GET', '/api/refund-requests'],
            ['POST', "/api/refund-requests/{$refund->id}/approve"],
            ['POST', "/api/refund-requests/{$refund->id}/reject"],
            ['POST', "/api/refund-requests/{$refund->id}/report-execution"],
            ['POST', "/api/refund-requests/{$refund->id}/acknowledge"],
            ['GET', '/api/admin/refunds'],
        ];
        $payload = [
            'slot_ids' => [1], 'court_id' => $court->id, 'slots' => [now()->addDays(3)->setTime(10, 0)->toIso8601String()],
            'reason' => 'retired route probe', 'transfer_reference' => 'FT-PROBE', 'evidence' => 'probe', 'accepted' => true,
        ];

        foreach (['customer' => $customer, 'owner' => $owner, 'platform admin' => $platformAdmin] as $role => $caller) {
            foreach ($routes as [$method, $uri]) {
                $before = $this->snapshot();

                $this->actingAs($caller)->withHeaders([
                    'X-Organization-ID' => (string) $organization->id,
                    'Idempotency-Key' => "retired {$role} {$method} {$uri}",
                ])->json($method, $uri, $payload)->assertNotFound();

                $this->assertSame($before, $this->snapshot(), "{$method} {$uri} as {$role} changed a watched table.");
            }
        }

        $this->assertDatabaseHas('owner_refund_obligations', ['id' => $refund->id, 'status' => 'requested', 'amount' => 200000]);
        $this->assertSame('confirmed', $reservation->fresh()->status);
    }

    /** @return array<string, array<string, mixed>> */
    private function snapshot(): array
    {
        return collect(self::WATCHED_TABLES)->mapWithKeys(fn (string $table): array => [
            $table => (array) DB::selectOne("select count(*) as total, md5(coalesce(string_agg(t::text, '|' order by t::text), '')) as checksum from {$table} t"),
        ])->all();
    }
}
