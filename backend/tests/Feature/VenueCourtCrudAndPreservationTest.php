<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\SportZone;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class VenueCourtCrudAndPreservationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_venue_crud_and_amenities_persistence(): void
    {
        $admin = $this->admin();
        $org = Organization::create(['name' => 'Aura Group', 'slug' => 'aura-group-'.uniqid()]);

        // 1. Create venue with amenities
        $createRes = $this->actingAs($admin)->postJson('/api/v1/admin/venues', [
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
        $updateRes = $this->actingAs($admin)->patchJson("/api/v1/admin/venues/{$venueId}", [
            'address' => '88 Cau Giay, Updated, Hanoi',
            'amenities' => ['wifi', 'parking', 'shower', 'cafe', 'lounge'],
        ]);

        $updateRes->assertOk();

        // 3. Public view reflects the saved amenities and details
        $publicRes = $this->getJson("/api/v1/venues/{$slug}");
        $publicRes->assertOk()
            ->assertJsonPath('address', '88 Cau Giay, Updated, Hanoi')
            ->assertJsonPath('amenities', ['wifi', 'parking', 'shower', 'cafe', 'lounge']);
    }

    public function test_sport_zone_crud_and_slot_duration_association(): void
    {
        $admin = $this->admin();
        $org = Organization::create(['name' => 'Aura District 7 Org', 'slug' => 'aura-d7-org-'.uniqid(), 'status' => 'active', 'partner_status' => 'approved']);
        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Aura District 7',
            'slug' => 'aura-d7-'.uniqid(),
            'address' => 'Nguyen Van Linh, D7',
            'google_maps_url' => 'https://maps.google.com/?q=aura+d7',
            'is_active' => true,
        ]);

        // 1. Create sport zone
        $createRes = $this->actingAs($admin)->postJson('/api/v1/admin/sport-zones', [
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
        $updateRes = $this->actingAs($admin)->patchJson("/api/v1/admin/sport-zones/{$zoneId}", [
            'slot_duration' => 90,
        ]);

        $updateRes->assertOk()
            ->assertJsonPath('slot_duration', 90);

        // 3. Public view of sport zone
        $publicRes = $this->getJson("/api/v1/sport-zones/{$zoneId}");
        $publicRes->assertOk()
            ->assertJsonPath('id', $zoneId)
            ->assertJsonPath('sport_type', 'pickleball')
            ->assertJsonPath('slot_duration', 90);
    }

    public function test_court_crud_amenities_and_public_detail_integration(): void
    {
        $admin = $this->admin();
        $org = Organization::create(['name' => 'Aura Da Nang Org', 'slug' => 'aura-danang-org-'.uniqid(), 'status' => 'active', 'partner_status' => 'approved']);
        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Aura Da Nang',
            'slug' => 'aura-danang-'.uniqid(),
            'address' => 'Vo Nguyen Giap, Da Nang',
            'google_maps_url' => 'https://maps.google.com/?q=danang',
            'is_active' => true,
        ]);
        $zone = SportZone::create([
            'venue_id' => $venue->id,
            'name' => 'Badminton Zone',
            'sport_type' => 'badminton',
            'slot_duration' => 60,
            'is_active' => true,
        ]);

        // 1. Create Court with custom amenities
        $courtRes = $this->actingAs($admin)->postJson('/api/v1/admin/courts', [
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
        $detailRes = $this->getJson("/api/v1/courts/{$courtSlug}");
        $detailRes->assertOk()
            ->assertJsonPath('id', $courtId)
            ->assertJsonPath('slug', $courtSlug)
            ->assertJsonPath('amenities', ['wifi', 'pro_shop', 'air_conditioning'])
            ->assertJsonPath('venue.name', 'Aura Da Nang');

        // 3. Public list includes court
        $listRes = $this->getJson('/api/v1/courts');
        $listRes->assertOk();
        $courtInList = collect($listRes->json())->firstWhere('id', $courtId);
        $this->assertNotNull($courtInList);
        $this->assertSame(['wifi', 'pro_shop', 'air_conditioning'], $courtInList['amenities']);

        // 4. Update court permitted details
        $updateRes = $this->actingAs($admin)->patchJson("/api/v1/admin/courts/{$courtId}", [
            'description' => 'Updated BWF Thảm chuẩn quốc tế',
            'amenities' => ['wifi', 'pro_shop', 'air_conditioning', 'led_lighting'],
        ]);

        $updateRes->assertOk()
            ->assertJsonPath('amenities', ['wifi', 'pro_shop', 'air_conditioning', 'led_lighting']);

        // 5. Refreshed public court view reflects saved details
        $refreshedRes = $this->getJson("/api/v1/courts/{$courtSlug}");
        $refreshedRes->assertOk()
            ->assertJsonPath('amenities', ['wifi', 'pro_shop', 'air_conditioning', 'led_lighting']);
    }

    public function test_warm_database_court_catalog_reflects_the_first_court_update(): void
    {
        config(['cache.default' => 'database', 'cache.prefix' => 'court-catalog-regression-']);
        Cache::forget('courts_version');
        $admin = $this->admin();
        $org = Organization::create([
            'name' => 'Catalog Org',
            'slug' => 'catalog-org-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Catalog Venue',
            'slug' => 'catalog-venue-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = Court::create([
            'venue_id' => $venue->id,
            'name' => 'Original Court',
            'slug' => 'catalog-court-'.uniqid(),
            'is_active' => true,
        ]);
        $url = '/api/v1/courts?venue_id='.$venue->id;
        $this->getJson($url)->assertOk()->assertJsonPath('0.name', 'Original Court');

        $this->actingAs($admin)->patchJson('/api/v1/admin/courts/'.$court->id, [
            'name' => 'Updated Court',
        ])->assertOk();

        $this->getJson($url)->assertOk()->assertJsonPath('0.name', 'Updated Court');
    }

    public function test_court_surface_and_capacity_round_trip_without_mutating_existing_bookings(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        $org = Organization::create(['name' => 'Specification Arena Org', 'slug' => 'spec-arena-org-'.uniqid(), 'status' => 'active', 'partner_status' => 'approved']);
        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Specification Arena',
            'slug' => 'specification-arena-'.uniqid(),
            'address' => '22 Court Street',
            'is_active' => true,
        ]);
        SportZone::create([
            'venue_id' => $venue->id,
            'name' => 'Badminton Zone',
            'sport_type' => 'badminton',
            'slot_duration' => 60,
            'is_active' => true,
        ]);

        $created = $this->actingAs($admin)->postJson('/api/v1/admin/courts', [
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

        $this->actingAs($admin)->patchJson("/api/v1/admin/courts/{$courtId}", [
            'surface_type' => 'wood',
            'capacity' => 6,
        ])->assertOk()
            ->assertJsonPath('surface_type', 'wood')
            ->assertJsonPath('capacity', 6);

        $this->getJson("/api/v1/courts/{$created->json('slug')}")
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
        $admin = $this->admin();
        $venue = Venue::create([
            'name' => 'Validation Arena',
            'slug' => 'validation-arena-'.uniqid(),
            'address' => '23 Court Street',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->postJson('/api/v1/admin/courts', [
            'name' => 'Invalid Court',
            'venue_id' => $venue->id,
            'surface_type' => 'liquid',
            'capacity' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['surface_type', 'capacity']);
    }

    public function test_court_deactivation_preserves_historical_bookings_and_rejects_new_bookings(): void
    {
        $admin = $this->admin();
        $org = Organization::create(['name' => 'Aura Club', 'slug' => 'aura-club-'.uniqid()]);
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

        // 1. Create historical V1 booking on this court
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
            'starts_at' => now()->subDay()->setHour(14),
            'ends_at' => now()->subDay()->setHour(15),
            'unit_price' => 250000,
            'status' => 'confirmed',
        ]);

        // Create historical V2 reservation on this court
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
        $deactivateRes = $this->actingAs($admin)->deleteJson("/api/v1/admin/courts/{$court->id}");
        $deactivateRes->assertOk();

        // 3. Court lifecycle preservation: record remains in DB with is_active = false
        $court->refresh();
        $this->assertFalse($court->is_active);
        $this->assertDatabaseHas('courts', ['id' => $court->id, 'is_active' => false]);

        // 4. Historical booking and reservation remain intact and queryable
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'court_id' => $court->id]);
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'court_id' => $court->id]);

        $bookingCheck = $this->actingAs($user)->getJson("/api/v1/bookings/{$booking->code}");
        $bookingCheck->assertOk()
            ->assertJsonPath('court.id', $court->id);

        // 5. Inactive court is hidden from public list
        $publicList = $this->getJson('/api/v1/courts');
        $publicList->assertOk();
        $this->assertNull(collect($publicList->json())->firstWhere('id', $court->id));

        // 6. Admin list STILL includes inactive court (for administrative record)
        $adminList = $this->actingAs($admin)->getJson('/api/v1/admin/courts');
        $adminList->assertOk();
        $adminCourt = collect($adminList->json())->firstWhere('id', $court->id);
        $this->assertNotNull($adminCourt);
        $this->assertFalse($adminCourt['is_active']);

        // 7. Public detail is hidden for a deactivated court (consistent with public list + new-booking rejection)
        $detailCheck = $this->getJson("/api/v1/courts/{$court->slug}");
        $detailCheck->assertNotFound();

        // 8. New bookings and reservations on the deactivated court are rejected
        $newBookingAttempt = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [now()->addDay()->setTime(10, 0, 0)->toIso8601String()],
        ]);
        $newBookingAttempt->assertNotFound();

        $newQuoteAttempt = $this->actingAs($user)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->withHeader('Idempotency-Key', uniqid('quote-'))
            ->postJson('/api/v2/price-quotes', [
                'court_id' => $court->id,
                'slots' => [now()->addDay()->setTime(10, 0, 0)->toIso8601String()],
            ]);
        $newQuoteAttempt->assertNotFound();
    }
}
