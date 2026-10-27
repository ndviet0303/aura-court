<?php

namespace Tests\Feature\Module02;

use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsVenues;
use Tests\TestCase;

/** FR-26, FR-27, BR-26, BR-29, BR-30 (new venue); design D11, D12. UC-2.3, UC-2.4. */
class VenueInformationTest extends TestCase
{
    use BuildsVenues;
    use RefreshDatabase;

    private const VALID = [
        'name' => 'Sân cầu lông Phú Nhuận',
        'address' => '12 Phan Xích Long, Phú Nhuận',
        'phone' => '0281234567',
        'email' => 'lienhe@phunhuan.test',
        'description' => 'Sáu sân thảm, có chỗ để xe.',
        'amenities' => ['Wifi', 'Bãi xe'],
    ];

    private function create(User $owner, int $organizationId, array $body)
    {
        return $this->actingAs($owner)->postJson("/api/partner/organizations/{$organizationId}/venues", $body);
    }

    public function test_fr_26_new_venue_is_trimmed_attached_to_the_organization_and_inactive(): void
    {
        $organization = $this->openVenue()->organization;
        $owner = $this->ownerOf($organization);

        $response = $this->create($owner, $organization->id, ['name' => '  Sân Phú Nhuận  ', 'email' => ' lienhe@phunhuan.test '] + self::VALID)->assertCreated();

        $venue = Venue::query()->findOrFail($response->json('data.id'));
        $this->assertSame([$organization->id, 'Sân Phú Nhuận', 'lienhe@phunhuan.test', Venue::STATUS_INACTIVE], [$venue->organization_id, $venue->name, $venue->email, $venue->status]);
        $this->assertSame(['Wifi', 'Bãi xe'], $venue->amenities);
    }

    public function test_br_25_only_the_owner_of_that_organization_creates(): void
    {
        $venue = $this->openVenue();
        $outsider = $this->ownerOf($this->openVenue()->organization);
        $count = Venue::count();

        $this->create($this->staffOf($venue), $venue->organization_id, self::VALID)->assertForbidden();
        $this->create($outsider, $venue->organization_id, self::VALID)->assertForbidden();
        $this->create($this->platformAdmin(), $venue->organization_id, self::VALID)->assertForbidden();
        $this->assertSame($count, Venue::count());
    }

    public function test_br_26_boundaries_name_each_wrong_field(): void
    {
        $organization = $this->openVenue()->organization;
        $owner = $this->ownerOf($organization);
        $invalid = [
            'name' => ['name' => ' a '],
            'address' => ['address' => str_repeat('a', 9)],
            'phone' => ['phone' => '028123456'],
            'email' => ['email' => 'lien he@phunhuan.test'],
            'description' => ['description' => str_repeat('a', 2001)],
            'amenities' => ['amenities' => array_map(fn ($i) => "Tiện ích {$i}", range(1, 21))],
            'amenities.0' => ['amenities' => [str_repeat('a', 51)]],
        ];
        foreach ($invalid as $field => $override) {
            $this->create($owner, $organization->id, $override + self::VALID)->assertStatus(422)->assertJsonValidationErrors($field);
        }
        foreach (['name', 'address', 'phone', 'email'] as $required) {
            $this->create($owner, $organization->id, [$required => '   '] + self::VALID)->assertStatus(422)->assertJsonValidationErrors($required);
        }
        $this->create($owner, $organization->id, ['name' => str_repeat('a', 100), 'address' => str_repeat('a', 255), 'phone' => '+84912345678', 'email' => 'a@b.vn'] + self::VALID)->assertCreated();
    }

    public function test_fr_27_an_invalid_update_keeps_the_stored_information(): void
    {
        $organization = $this->openVenue()->organization;
        $owner = $this->ownerOf($organization);
        $id = $this->create($owner, $organization->id, self::VALID)->json('data.id');
        $url = "/api/partner/organizations/{$organization->id}/venues/{$id}";

        $this->actingAs($owner)->putJson($url, ['phone' => '123'] + self::VALID)->assertStatus(422)->assertJsonValidationErrors('phone')->assertJsonMissingValidationErrors('name');
        $this->assertSame(self::VALID['phone'], Venue::find($id)->phone);

        $this->actingAs($owner)->putJson($url, ['name' => 'Sân Phú Nhuận mới'] + self::VALID)->assertOk();
        $this->assertSame('Sân Phú Nhuận mới', Venue::find($id)->name);
        $this->actingAs($this->staffOf(Venue::find($id)))->putJson($url, self::VALID)->assertForbidden();
    }

    public function test_br_29_images_are_uploaded_jpg_or_png_up_to_5_mb_and_at_most_10(): void
    {
        Storage::fake('public', ['url' => config('filesystems.disks.public.url')]);
        $venue = $this->openVenue();
        $owner = $this->ownerOf($venue->organization);

        foreach ([['a.gif', 10, 'image/gif'], ['a.pdf', 10, 'application/pdf'], ['big.png', 5121, 'image/png']] as [$name, $kb, $mime]) {
            $this->actingAs($owner)->post('/api/admin/upload', ['image' => UploadedFile::fake()->create($name, $kb, $mime)], ['Accept' => 'application/json'])->assertStatus(422);
        }
        $this->assertSame([], Storage::disk('public')->allFiles(), 'a refused file is not stored');
        $this->actingAs(User::factory()->create())->post('/api/admin/upload', ['image' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])->assertForbidden();

        $url = $this->actingAs($owner)->post('/api/admin/upload', ['image' => UploadedFile::fake()->image('a.jpg')->size(5120)], ['Accept' => 'application/json'])->assertOk()->json('url');
        $this->assertCount(1, Storage::disk('public')->allFiles());

        $this->create($owner, $venue->organization_id, ['images' => [$url]] + self::VALID)->assertCreated();
        $this->create($owner, $venue->organization_id, ['images' => array_fill(0, 11, $url)] + self::VALID)->assertStatus(422)->assertJsonValidationErrors('images');
        $this->create($owner, $venue->organization_id, ['images' => ['https://example.com/a.jpg']] + self::VALID)->assertStatus(422)->assertJsonValidationErrors('images.0');
    }

    public function test_task_2_16_booking_policy_no_longer_sets_hold_time_or_continuity(): void
    {
        $venue = $this->openVenue();
        $owner = $this->ownerOf($venue->organization);
        $url = "/api/partner/organizations/{$venue->organization_id}/venues/{$venue->id}/booking-policy";
        $settings = [
            'booking' => ['max_days_ahead' => 30, 'min_duration_minutes' => 60, 'max_duration_minutes' => 240],
            'transfer' => ['cutoff_hours' => 24, 'max_consideration_percent' => 0, 'free_transfer_allowed' => true],
        ];

        foreach (['hold_ttl_minutes' => 15, 'require_continuity' => true] as $key => $value) {
            $body = $settings;
            $body['booking'][$key] = $value;
            $this->actingAs($owner)->putJson($url, ['settings' => $body])->assertStatus(422)->assertJsonValidationErrors('settings.booking');
        }
        $this->actingAs($owner)->putJson($url, ['settings' => $settings])->assertSuccessful();
        $this->assertSame($settings['booking'], $this->actingAs($owner)->getJson($url)->assertOk()->json('data.settings.booking'));

        // Policy changes after booking: a save adds a version and keeps the earlier one for bookings that captured it.
        $settings['booking']['max_days_ahead'] = 60;
        $this->actingAs($owner)->putJson($url, ['settings' => $settings])->assertSuccessful();
        $this->assertSame([30, 60], VenueBookingPolicy::query()->where('venue_id', $venue->id)->orderBy('version')->get()->map(fn ($p) => $p->settings['booking']['max_days_ahead'])->all());
        $this->actingAs($owner)->getJson($url)->assertJsonPath('data.settings.booking.max_days_ahead', 60);
    }
}
