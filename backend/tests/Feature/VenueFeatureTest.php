<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function venue(array $overrides = []): Venue
    {
        return Venue::create(array_merge([
            'name' => 'Địa điểm thử nghiệm',
            'slug' => 'dia-diem-thu-nghiem',
            'address' => '123 Đường Test, Quận 1',
            'google_maps_url' => 'https://www.google.com/maps/place/Aura+Court',
            'is_active' => true,
        ], $overrides));
    }

    public function test_admin_creates_unique_slug_and_renaming_keeps_the_share_link(): void
    {
        $admin = $this->admin();
        $payload = ['name' => 'Aura Hà Nội', 'address' => '1 Phố Test', 'google_maps_url' => 'https://maps.google.com/maps?q=aura'];

        $first = $this->actingAs($admin)->postJson('/api/v1/admin/venues', $payload)->assertCreated();
        $second = $this->actingAs($admin)->postJson('/api/v1/admin/venues', $payload)->assertCreated();
        $firstSlug = $first->json('slug');
        $second->assertJsonPath('slug', $firstSlug.'-2');

        $this->actingAs($admin)->patchJson('/api/v1/admin/venues/'.$first->json('id'), ['name' => 'Aura Hồ Chí Minh', ...$payload])
            ->assertOk()->assertJsonPath('slug', $firstSlug)->assertJsonPath('share_url', config('app.frontend_url').'/venues/'.$firstSlug.'/directions');
    }

    public function test_venue_rejects_non_google_maps_urls(): void
    {
        $this->actingAs($this->admin())->postJson('/api/v1/admin/venues', [
            'name' => 'Sai link', 'address' => 'Địa chỉ', 'google_maps_url' => 'https://example.com/maps',
        ])->assertUnprocessable()->assertJsonValidationErrors('google_maps_url');
    }

    public function test_inactive_venue_is_hidden_publicly_and_cannot_receive_bookings(): void
    {
        $venue = $this->venue(['slug' => 'inactive-venue']);
        $court = Court::create(['name' => 'Sân inactive', 'venue_id' => $venue->id, 'is_active' => true]);
        $venue->update(['is_active' => false]);

        $this->getJson('/api/v1/venues/'.$venue->slug)->assertNotFound();
        $this->getJson('/api/v1/courts')->assertOk()->assertJsonMissing(['id' => $court->id]);

        $customer = User::factory()->create();
        $this->actingAs($customer)->postJson('/api/v1/bookings', ['court_id' => $court->id, 'slots' => [now()->addDay()->setTime(10, 0)->toIso8601String()]])->assertNotFound();
    }

    public function test_regular_user_cannot_manage_venues_and_demo_mutations_roll_back(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/v1/admin/venues')->assertForbidden();

        $demo = User::factory()->create(['role' => 'admin', 'email' => 'venue-demo@demo.auracourt.vn']);
        $this->actingAs($demo)->postJson('/api/v1/admin/venues', [
            'name' => 'Demo venue', 'address' => 'Địa chỉ demo', 'google_maps_url' => 'https://maps.app.goo.gl/demo',
        ])->assertCreated()->assertHeader('X-Demo-Simulated', 'true');
        $this->assertDatabaseMissing('venues', ['name' => 'Demo venue']);
    }
}
