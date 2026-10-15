<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceRule;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\SportZone;
use App\Models\User;
use App\Models\Venue;
use App\Services\Reservation\LegacyBookingBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class VenueCourtCrudAndPreservationTest extends TestCase
{
    use RefreshDatabase;

    /** BR-23: venue and court management is the organization owner's job, not the platform admin's. */
    private function partnerOrg(string $name = 'Aura Partner'): Organization
    {
        return Organization::create(['name' => $name, 'slug' => 'org-'.uniqid(), 'status' => 'active', 'partner_status' => 'approved']);
    }

    public function test_venue_crud_and_amenities_persistence(): void
    {
        $org = $this->partnerOrg('Aura Group');
        $admin = $this->ownerOf($org);

        // 1. Create venue with amenities
        $createRes = $this->actingAs($admin)->postJson('/api/admin/venues', [
            'name' => 'Aura Stadium Hanoi',
            'address' => '88 Cau Giay, Hanoi',
            'google_maps_url' => 'https://maps.google.com/maps?q=aura+stadium',
            'amenities' => ['wifi', 'parking', 'shower', 'cafe'],
            'is_active' => true,
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('name.vi', 'Aura Stadium Hanoi')
            ->assertJsonPath('amenities', ['wifi', 'parking', 'shower', 'cafe']);

        $venueId = $createRes->json('id');
        $slug = $createRes->json('slug');

        $this->assertDatabaseHas('venues', [
            'id' => $venueId,
            'address' => '88 Cau Giay, Hanoi',
        ]);

        // 2. Update venue amenities
        $updateRes = $this->actingAs($admin)->patchJson("/api/admin/venues/{$venueId}", [
            'address' => '88 Cau Giay, Updated, Hanoi',
            'amenities' => ['wifi', 'parking', 'shower', 'cafe', 'lounge'],
            'latitude' => 21.0306,
            'longitude' => 105.783,
        ]);

        $updateRes->assertOk();
        $this->actingAs($admin)->patchJson("/api/admin/venues/{$venueId}", ['latitude' => 21.03])
            ->assertUnprocessable()->assertJsonValidationErrors('longitude');
        $this->actingAs($admin)->patchJson("/api/admin/venues/{$venueId}", ['latitude' => 91, 'longitude' => 105.78])
            ->assertUnprocessable()->assertJsonValidationErrors('latitude');

        // 3. Once the platform publishes it, the public view reflects the saved amenities and details
        Venue::whereKey($venueId)->update(['status' => 'published', 'is_active' => true]);
        $publicRes = $this->getJson("/api/venues/{$slug}");
        $publicRes->assertOk()
            ->assertJsonPath('address', '88 Cau Giay, Updated, Hanoi')
            ->assertJsonPath('amenities', ['wifi', 'parking', 'shower', 'cafe', 'lounge'])
            ->assertJsonPath('latitude', 21.0306)
            ->assertJsonPath('longitude', 105.783);
    }

    public function test_sport_zone_crud_and_slot_duration_association(): void
    {
        $org = Organization::create(['name' => 'Aura District 7 Org', 'slug' => 'aura-d7-org-'.uniqid(), 'status' => 'active', 'partner_status' => 'approved']);
        $admin = $this->ownerOf($org);
        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Aura District 7',
            'slug' => 'aura-d7-'.uniqid(),
            'address' => 'Nguyen Van Linh, D7',
            'google_maps_url' => 'https://maps.google.com/?q=aura+d7',
            'is_active' => true,
        ]);

        // 1. Create sport zone
        $createRes = $this->actingAs($admin)->postJson('/api/admin/sport-zones', [
            'venue_id' => $venue->id,
            'name' => ['vi' => 'Khu Pickleball VIP', 'en' => 'Pickleball VIP Zone'],
            'sport_type' => 'pickleball',
            'slot_duration' => 60,
            'is_active' => true,
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('sport_type', 'pickleball')
            ->assertJsonPath('slot_duration', 60);

        $zoneId = $createRes->json('id');

        // 2. Update sport zone
        $updateRes = $this->actingAs($admin)->patchJson("/api/admin/sport-zones/{$zoneId}", [
            'slot_duration' => 90,
        ]);

        $updateRes->assertOk()
            ->assertJsonPath('slot_duration', 90);

        // 3. Public view of sport zone
        $publicRes = $this->getJson("/api/sport-zones/{$zoneId}");
        $publicRes->assertOk()
            ->assertJsonPath('id', $zoneId)
            ->assertJsonPath('sport_type', 'pickleball')
            ->assertJsonPath('slot_duration', 90);
    }

    public function test_court_crud_amenities_and_public_detail_integration(): void
    {
        $org = Organization::create(['name' => 'Aura Da Nang Org', 'slug' => 'aura-danang-org-'.uniqid(), 'status' => 'active', 'partner_status' => 'approved']);
        $admin = $this->ownerOf($org);
        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Aura Da Nang',
            'slug' => 'aura-danang-'.uniqid(),
            'address' => 'Vo Nguyen Giap, Da Nang',
            'google_maps_url' => 'https://maps.google.com/?q=danang',
            'district' => 'Hải Châu',
            'latitude' => 16.0544,
            'longitude' => 108.2022,
            'is_active' => true,
        ]);
        // Only priced courts are public; an organization-wide rule prices every court.
        PriceRule::create(['organization_id' => $org->id, 'type' => 'base', 'price' => 100000, 'is_active' => true]);
        $zone = SportZone::create([
            'venue_id' => $venue->id,
            'name' => 'Badminton Zone',
            'sport_type' => 'badminton',
            'slot_duration' => 60,
            'is_active' => true,
        ]);

        // 1. Create Court with custom amenities
        $courtRes = $this->actingAs($admin)->postJson('/api/admin/courts', [
            'name' => 'San 01 BWF Da Nang',
            'venue_id' => $venue->id,
            'sport_zone_id' => $zone->id,
            'description' => 'Tham thi dau tieu chuan BWF',
            'amenities' => ['wifi', 'pro_shop', 'air_conditioning'],
            'is_active' => true,
        ]);

        $courtRes->assertStatus(201)
            ->assertJsonPath('amenities', ['wifi', 'pro_shop', 'air_conditioning']);

        $courtId = $courtRes->json('id');
        $courtSlug = $courtRes->json('slug');

        // 2. Public detail view of court via /courts/{slug}
        $detailRes = $this->getJson("/api/courts/{$courtSlug}");
        $detailRes->assertOk()
            ->assertJsonPath('id', $courtId)
            ->assertJsonPath('slug', $courtSlug)
            ->assertJsonPath('amenities', ['wifi', 'pro_shop', 'air_conditioning'])
            ->assertJsonPath('venue.name', 'Aura Da Nang');

        // 3. Public list includes court
        $listRes = $this->getJson('/api/courts');
        $listRes->assertOk();
        $courtInList = collect($listRes->json())->firstWhere('id', $courtId);
        $this->assertNotNull($courtInList);
        $this->assertSame(['wifi', 'pro_shop', 'air_conditioning'], $courtInList['amenities']);
        // The home page groups courts by district and sorts them by distance from these coordinates.
        $this->assertSame(['Hải Châu', 16.0544, 108.2022], [$courtInList['venue']['district'], $courtInList['venue']['latitude'], $courtInList['venue']['longitude']]);

        // 4. Update court permitted details
        $updateRes = $this->actingAs($admin)->patchJson("/api/admin/courts/{$courtId}", [
            'description' => 'Updated BWF Thảm chuẩn quốc tế',
            'amenities' => ['wifi', 'pro_shop', 'air_conditioning', 'led_lighting'],
        ]);

        $updateRes->assertOk()
            ->assertJsonPath('amenities', ['wifi', 'pro_shop', 'air_conditioning', 'led_lighting']);

        // 5. Refreshed public court view reflects saved details
        $refreshedRes = $this->getJson("/api/courts/{$courtSlug}");
        $refreshedRes->assertOk()
            ->assertJsonPath('amenities', ['wifi', 'pro_shop', 'air_conditioning', 'led_lighting']);
    }

    public function test_warm_database_court_catalog_reflects_the_first_court_update(): void
    {
        config(['cache.default' => 'database', 'cache.prefix' => 'court-catalog-regression-']);
        Cache::forget('courts_version');
        $org = Organization::create([
            'name' => 'Catalog Org',
            'slug' => 'catalog-org-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $admin = $this->ownerOf($org);
        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Catalog Venue',
            'slug' => 'catalog-venue-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);
        // Only priced courts are public; an organization-wide rule prices every court.
        PriceRule::create(['organization_id' => $org->id, 'type' => 'base', 'price' => 100000, 'is_active' => true]);
        $court = Court::create([
            'venue_id' => $venue->id,
            'name' => 'Original Court',
            'slug' => 'catalog-court-'.uniqid(),
            'is_active' => true,
        ]);
        $url = '/api/courts?venue_id='.$venue->id;
        $this->getJson($url)->assertOk()->assertJsonPath('0.name', 'Original Court');

        $this->actingAs($admin)->patchJson('/api/admin/courts/'.$court->id, [
            'name' => 'Updated Court',
        ])->assertOk();

        $this->getJson($url)->assertOk()->assertJsonPath('0.name', 'Updated Court');
    }

    public function test_court_surface_and_capacity_round_trip_without_mutating_existing_bookings(): void
    {
        $owner = User::factory()->create();
        $org = Organization::create(['name' => 'Specification Arena Org', 'slug' => 'spec-arena-org-'.uniqid(), 'status' => 'active', 'partner_status' => 'approved']);
        $admin = $this->ownerOf($org);
        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Specification Arena',
            'slug' => 'specification-arena-'.uniqid(),
            'address' => '22 Court Street',
            'is_active' => true,
        ]);
        // Only priced courts are public; an organization-wide rule prices every court.
        PriceRule::create(['organization_id' => $org->id, 'type' => 'base', 'price' => 100000, 'is_active' => true]);
        SportZone::create([
            'venue_id' => $venue->id,
            'name' => 'Badminton Zone',
            'sport_type' => 'badminton',
            'slot_duration' => 60,
            'is_active' => true,
        ]);

        $created = $this->actingAs($admin)->postJson('/api/admin/courts', [
            'name' => 'Court with specifications',
            'venue_id' => $venue->id,
            'surface_type' => 'synthetic',
            'capacity' => 4,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('surface_type', 'synthetic')
            ->assertJsonPath('capacity', 4);

        $courtId = (int) $created->json('id');
        $booking = Booking::create([
            'code' => 'SPEC-'.strtoupper(uniqid()),
            'user_id' => $owner->id,
            'court_id' => $courtId,
            'total_amount' => 220000,
            'status' => 'confirmed',
        ]);

        $this->actingAs($admin)->patchJson("/api/admin/courts/{$courtId}", [
            'surface_type' => 'wood',
            'capacity' => 6,
        ])->assertOk()
            ->assertJsonPath('surface_type', 'wood')
            ->assertJsonPath('capacity', 6);

        $this->getJson("/api/courts/{$created->json('slug')}")
            ->assertOk()
            ->assertJsonPath('surface_type', 'wood')
            ->assertJsonPath('capacity', 6);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'court_id' => $courtId,
            'total_amount' => 220000,
            'status' => 'confirmed',
        ]);
    }

    public function test_court_surface_and_capacity_validation_rejects_unknown_or_impossible_values(): void
    {
        $org = $this->partnerOrg();
        $admin = $this->ownerOf($org);
        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Validation Arena',
            'slug' => 'validation-arena-'.uniqid(),
            'address' => '23 Court Street',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->postJson('/api/admin/courts', [
            'name' => 'Invalid Court',
            'venue_id' => $venue->id,
            'surface_type' => 'liquid',
            'capacity' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['surface_type', 'capacity']);
    }

    public function test_court_deactivation_preserves_historical_bookings_and_rejects_new_bookings(): void
    {
        $org = $this->partnerOrg('Aura Club');
        $admin = $this->ownerOf($org);
        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Aura Central Arena',
            'slug' => 'aura-central-'.uniqid(),
            'address' => '100 Dien Bien Phu, HCMC',
            'google_maps_url' => 'https://maps.google.com/?q=aura+central',
            'is_active' => true,
        ]);
        $zone = SportZone::create([
            'venue_id' => $venue->id,
            'name' => 'Tennis Court Zone',
            'sport_type' => 'tennis',
            'slot_duration' => 60,
            'is_active' => true,
        ]);
        $court = Court::create([
            'name' => 'Tennis Court 1',
            'slug' => 'tennis-court-1-'.uniqid(),
            'venue_id' => $venue->id,
            'sport_zone_id' => $zone->id,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);

        // 1. Create a historical legacy booking on this court and its reservation mirror
        $booking = Booking::create([
            'code' => 'BK-HIST-001',
            'user_id' => $user->id,
            'court_id' => $court->id,
            'total_amount' => 250000,
            'status' => 'confirmed',
        ]);
        BookingSlot::create([
            'booking_id' => $booking->id,
            'court_id' => $court->id,
            'starts_at' => now()->subDay()->setHour(16),
            'ends_at' => now()->subDay()->setHour(17),
            'unit_price' => 250000,
            'status' => 'confirmed',
        ]);
        app(LegacyBookingBackfillService::class)->backfillBooking($booking);

        // Create a historical reservation on this court
        $reservation = Reservation::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'court_id' => $court->id,
            'code' => 'RES-HIST-001',
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 250000,
        ]);
        ReservationSlot::create([
            'organization_id' => $org->id,
            'reservation_id' => $reservation->id,
            'court_id' => $court->id,
            'starts_at' => now()->subDay()->setHour(14),
            'ends_at' => now()->subDay()->setHour(15),
            'unit_price' => 250000,
            'status' => 'confirmed',
        ]);

        // 2. Operator deactivates the court
        $deactivateRes = $this->actingAs($admin)->deleteJson("/api/admin/courts/{$court->id}");
        $deactivateRes->assertOk();

        // 3. Court lifecycle preservation: record remains in DB with is_active = false
        $court->refresh();
        $this->assertFalse($court->is_active);
        $this->assertDatabaseHas('courts', ['id' => $court->id, 'is_active' => false]);

        // 4. Historical booking and reservation remain intact and queryable
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'court_id' => $court->id]);
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'court_id' => $court->id]);

        $bookingCheck = $this->actingAs($user)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->getJson('/api/reservations/LEGACY-BK-HIST-001');
        $bookingCheck->assertOk()
            ->assertJsonPath('reservation.court.id', $court->id)
            ->assertJsonPath('reservation.legacy_mapping.0.legacy_code', 'BK-HIST-001');

        // 5. Inactive court is hidden from public list
        $publicList = $this->getJson('/api/courts');
        $publicList->assertOk();
        $this->assertNull(collect($publicList->json())->firstWhere('id', $court->id));

        // 6. Admin list STILL includes inactive court (for administrative record)
        $adminList = $this->actingAs($admin)->getJson('/api/admin/courts');
        $adminList->assertOk();
        $adminCourt = collect($adminList->json())->firstWhere('id', $court->id);
        $this->assertNotNull($adminCourt);
        $this->assertFalse($adminCourt['is_active']);

        // 7. Public detail is hidden for a deactivated court (consistent with public list + new-booking rejection)
        $detailCheck = $this->getJson("/api/courts/{$court->slug}");
        $detailCheck->assertNotFound();

        // 8. New bookings and reservations on the deactivated court are rejected
        $newBookingAttempt = $this->actingAs($user)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->withHeader('Idempotency-Key', uniqid('checkout-'))
            ->postJson('/api/reservations/checkout', [
                'court_id' => $court->id,
                'slots' => [now()->addDay()->setTime(10, 0, 0)->toIso8601String()],
            ]);
        $newBookingAttempt->assertNotFound();

        $newQuoteAttempt = $this->actingAs($user)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->withHeader('Idempotency-Key', uniqid('quote-'))
            ->postJson('/api/price-quotes', [
                'court_id' => $court->id,
                'slots' => [now()->addDay()->setTime(10, 0, 0)->toIso8601String()],
            ]);
        $newQuoteAttempt->assertNotFound();
    }

    public function test_admin_can_fetch_court_prices_and_listing_has_price_slots_count(): void
    {
        $org = $this->partnerOrg();
        $admin = $this->ownerOf($org);
        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => ['vi' => 'Cụm Sân Test Giá', 'en' => 'Test Venue'],
            'slug' => 'test-venue-'.uniqid(),
            'address' => '123 Test St',
            'is_active' => true,
        ]);
        $court = Court::create([
            'name' => 'Sân Giá Test',
            'venue_id' => $venue->id,
            'is_active' => true,
        ]);

        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'price' => 120000,
            'is_active' => true,
        ]);

        // 1. Admin courts list contains price_slots_count
        $listRes = $this->actingAs($admin)->getJson('/api/admin/courts');
        $listRes->assertOk();
        $courtData = collect($listRes->json())->firstWhere('id', $court->id);
        $this->assertNotNull($courtData);
        $this->assertEquals(1, $courtData['price_slots_count']);

        // 2. Admin can fetch single court prices on demand
        $pricesRes = $this->actingAs($admin)->getJson("/api/admin/courts/{$court->id}/prices");
        $pricesRes->assertOk();
        $pricesRes->assertJsonCount(1);
        $pricesRes->assertJsonFragment([
            'court_id' => $court->id,
            'weekday' => 1,
            'price' => 120000,
        ]);
    }
}
