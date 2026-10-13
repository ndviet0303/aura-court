<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\Court;
use App\Models\OrganizationMember;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\User;
use App\Services\Reservation\LegacyBookingBackfillService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyReservationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_history_spans_organizations_with_legacy_mapping_and_review(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $customer = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create(['organization_id' => $fixture['org1']['org']->id, 'user_id' => $customer->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);
        $own = $this->reservation($customer, $fixture['org1']['court1'], 'RSV-MINE-ORG1', now()->addDays(2));
        $foreign = $this->reservation($customer, $fixture['org2']['court'], 'RSV-MINE-ORG2', now()->subDays(2));
        Review::create(['user_id' => $customer->id, 'court_id' => $foreign->court_id, 'reservation_id' => $foreign->id, 'rating' => 5, 'body' => 'Tốt']);
        $booking = Booking::create(['code' => 'BK-MINE-LEGACY', 'user_id' => $customer->id, 'court_id' => $fixture['org1']['court2']->id, 'total_amount' => 90000, 'status' => 'completed']);
        $booking->slots()->create(['court_id' => $booking->court_id, 'starts_at' => now()->subDays(5), 'ends_at' => now()->subDays(5)->addHour(), 'unit_price' => 90000, 'status' => 'confirmed']);
        app(LegacyBookingBackfillService::class)->backfillBooking($booking);
        $this->reservation($fixture['org1']['owner'], $fixture['org1']['court1'], 'RSV-NOT-MINE', now()->addDays(3));

        $response = $this->actingAs($customer)->withHeader('X-Organization-ID', (string) $fixture['org1']['org']->id)
            ->getJson('/api/me/reservations')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('per_page', 20);

        $items = collect($response->json('data'))->keyBy('code');
        $this->assertEqualsCanonicalizing([$own->code, $foreign->code, 'LEGACY-BK-MINE-LEGACY'], $items->keys()->all());
        $this->assertSame($fixture['org2']['venue']->id, $items[$foreign->code]['court']['venue']['id']);
        $this->assertCount(1, $items[$foreign->code]['slots']);
        $this->assertCount(1, $items[$foreign->code]['entitlements']);
        $this->assertSame(5, $items[$foreign->code]['review']['rating']);
        $this->assertNull($items[$own->code]['review']);
        $this->assertSame([], $items[$own->code]['legacy_mapping']);
        $legacy = $items['LEGACY-BK-MINE-LEGACY'];
        $this->assertSame('legacy', $legacy['status']);
        $this->assertSame('BK-MINE-LEGACY', $legacy['legacy_mapping'][0]['legacy_code']);
        $this->assertSame('completed', $legacy['legacy_mapping'][0]['legacy_status']);
    }

    private function reservation(User $user, Court $court, string $code, Carbon $start): Reservation
    {
        $organizationId = $court->venue->organization_id;
        $reservation = Reservation::create([
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'court_id' => $court->id,
            'code' => $code,
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 100000,
            'payment_model' => 'legacy_wallet_v1',
        ]);
        $slot = $reservation->slots()->create([
            'organization_id' => $organizationId,
            'court_id' => $court->id,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'unit_price' => 100000,
            'status' => 'confirmed',
        ]);
        $reservation->entitlements()->create([
            'organization_id' => $organizationId,
            'reservation_slot_id' => $slot->id,
            'user_id' => $user->id,
            'court_id' => $court->id,
            'starts_at' => $slot->starts_at,
            'ends_at' => $slot->ends_at,
            'amount' => 100000,
            'status' => 'active',
        ]);

        return $reservation;
    }
}
