<?php

namespace Tests\Feature\Venue;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\ReceivingConnection;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Models\VenueOperatingSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VenueProfilePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_description_location_and_images_round_trip_and_publish_from_reviewed_snapshot(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $reviewer = User::factory()->create(['role' => 'admin']);
        $organization = Organization::create([
            'name' => 'Profile Club',
            'slug' => 'profile-club',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => 'organization_owner',
            'status' => 'active',
        ]);
        $plan = PlatformPlan::create([
            'code' => 'PROFILE-PLAN',
            'name' => 'Profile Plan',
            'price' => 500000,
            'billing_cycle' => 'monthly',
            'max_venues' => 2,
            'max_courts' => 4,
            'terms_version' => '1',
            'terms_text' => 'Terms',
            'is_active' => true,
        ]);
        PlatformSubscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
        Sanctum::actingAs($owner);
        $base = "/api/v1/partner/organizations/{$organization->id}/venues";

        $created = $this->postJson($base, [
            'name' => 'Profile Venue',
            'address' => '123 Main Street',
            'city' => 'Da Nang',
            'district' => 'Hai Chau',
            'ward' => 'Thach Thang',
            'phone' => '+84236123456',
            'email' => 'hello@profile.test',
            'description' => 'Original public description.',
            'images' => ['https://images.test/front.jpg', 'https://images.test/court.jpg'],
            'google_maps_url' => 'https://maps.google.com/maps?q=123+Main+Street',
        ])->assertCreated();

        $venueId = (int) $created->json('data.id');
        $slug = (string) $created->json('data.slug');
        $detailUrl = "{$base}/{$venueId}";
        $this->assertDatabaseHas('venues', [
            'id' => $venueId,
            'city' => 'Da Nang',
            'district' => 'Hai Chau',
            'ward' => 'Thach Thang',
            'phone' => '+84236123456',
            'email' => 'hello@profile.test',
            'description' => 'Original public description.',
        ]);

        $this->putJson($detailUrl, [
            'phone' => '+84236111222',
            'description' => 'Reviewed public description.',
            'images' => ['https://images.test/reviewed-front.jpg'],
        ])->assertOk();
        $this->getJson($detailUrl)
            ->assertOk()
            ->assertJsonPath('data.phone', '+84236111222')
            ->assertJsonPath('data.description', 'Reviewed public description.')
            ->assertJsonPath('data.images.0', 'https://images.test/reviewed-front.jpg');

        $venue = Venue::findOrFail($venueId);
        Court::create(['venue_id' => $venueId, 'name' => 'Court 1', 'slug' => 'profile-court-1', 'is_active' => true]);
        $court = Court::where('venue_id', $venueId)->firstOrFail();
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => 1,
            'starts_at' => '06:00',
            'ends_at' => '22:00',
            'price' => 100000,
            'is_active' => true,
        ]);
        VenueBookingPolicy::create([
            'venue_id' => $venueId,
            'version' => 1,
            'created_by' => $owner->id,
            'effective_at' => now(),
            'settings' => [
                'booking' => ['hold_ttl_minutes' => 15, 'max_days_ahead' => 60, 'min_duration_minutes' => 60, 'max_duration_minutes' => 240, 'require_continuity' => true],
                'cancellation' => ['deadline_hours' => 24, 'fee_type' => 'none', 'fee_value' => 0, 'refund_due_hours' => 48],
                'change' => ['deadline_hours' => 12, 'fee_type' => 'none', 'fee_value' => 0],
                'arrival' => ['early_minutes' => 15, 'late_grace_minutes' => 10, 'no_show_after_minutes' => 30],
                'recurring' => ['payment_policy' => 'per_period', 'conflict_policy' => 'all_or_nothing', 'overdue_release_hours' => 24],
                'transfer' => ['cutoff_hours' => 2, 'max_consideration_percent' => 100, 'free_transfer_allowed' => false, 'seller_reimbursement_required' => true],
                'ancillary_credit_policy' => 'refund',
            ],
        ]);
        $hours = [];
        for ($day = 1; $day <= 7; $day++) {
            $hours[(string) $day] = ['closed' => false, 'intervals' => [['start' => '06:00', 'end' => '22:00']]];
        }
        VenueOperatingSchedule::create([
            'venue_id' => $venueId,
            'scope_key' => 'venue',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'version' => 1,
            'weekly_hours' => $hours,
            'created_by' => $owner->id,
        ]);
        $this->selectVerifiedReceiver($venue, $owner);

        $submit = $this->postJson("{$detailUrl}/submit-publication")->assertCreated();
        $revisionId = $submit->json('data.id');
        $this->assertSame('Reviewed public description.', $submit->json('data.configuration_snapshot.venue.description'));
        Sanctum::actingAs($reviewer);
        $this->postJson("/api/v1/admin/venues/revisions/{$revisionId}/review", ['decision' => 'approve'])
            ->assertOk();

        Sanctum::actingAs($owner);
        $this->putJson($detailUrl, [
            'description' => 'Unreviewed replacement text.',
            'images' => ['https://images.test/unreviewed.jpg'],
        ])->assertOk();
        $this->getJson("/api/v1/venues/{$slug}")
            ->assertOk()
            ->assertJsonPath('description', 'Reviewed public description.')
            ->assertJsonPath('phone', '+84236111222')
            ->assertJsonPath('images.0', 'https://images.test/reviewed-front.jpg');

        $secondRevision = $this->postJson("{$detailUrl}/submit-publication")->assertCreated();
        $revisionId = $secondRevision->json('data.id');
        Sanctum::actingAs($reviewer);
        $this->postJson("/api/v1/admin/venues/revisions/{$revisionId}/review", ['decision' => 'approve'])
            ->assertOk();

        $this->getJson("/api/v1/venues/{$slug}")
            ->assertOk()
            ->assertJsonPath('description', 'Unreviewed replacement text.')
            ->assertJsonPath('images.0', 'https://images.test/unreviewed.jpg');
    }

    private function selectVerifiedReceiver(Venue $venue, User $owner): void
    {
        $connection = new ReceivingConnection([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->id,
            'owner_user_id' => $owner->id,
            'provider' => 'sepay',
            'routing_id' => (string) Str::uuid(),
            'bank_code' => 'MBBank',
            'status' => ReceivingConnection::STATUS_VERIFIED,
            'credential_version' => 1,
            'owner_attested_at' => now(),
            'verified_at' => now(),
            'effective_from' => now(),
        ]);
        $connection->setAccountDetails('Venue Owner', '0123456789');
        $connection->webhook_secret_ciphertext = Crypt::encryptString('verified-test-secret');
        $connection->save();
        $venue->forceFill(['current_receiving_connection_id' => $connection->id])->save();
    }
}
