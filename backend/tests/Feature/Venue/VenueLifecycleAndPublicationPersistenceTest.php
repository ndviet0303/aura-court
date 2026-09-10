<?php

namespace Tests\Feature\Venue;

use App\Models\Organization;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePublicationRevision;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueLifecycleAndPublicationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_persist_venue_lifecycle_and_is_active_projection(): void
    {
        $org = Organization::create([
            'name' => 'Saigon Sports Center',
            'slug' => 'saigon-sports-center',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Sân Cầu Lông Saigon',
            'slug' => 'san-cau-long-saigon',
            'address' => '456 Nguyen Trai, Q5, HCMC',
            'status' => 'draft',
            'is_active' => false,
        ]);

        $this->assertTrue($venue->isDraft());
        $this->assertFalse($venue->isActive());
        $this->assertFalse($venue->is_active); // Projection is false

        // Transition to active
        $venue->update([
            'status' => 'active',
            'published_at' => now(),
            'approved_public_snapshot' => [
                'name' => 'Sân Cầu Lông Saigon',
                'address' => '456 Nguyen Trai, Q5, HCMC',
            ],
        ]);

        $this->assertTrue($venue->isActive());
        $this->assertTrue($venue->is_active); // Projection is true
        $this->assertNotNull($venue->published_at);
        $this->assertEquals('Sân Cầu Lông Saigon', $venue->approved_public_snapshot['name']);

        // Setting is_active to false should transition status to temporarily_closed
        $venue->is_active = false;
        $venue->save();

        $this->assertEquals('temporarily_closed', $venue->fresh()->status);
        $this->assertTrue($venue->fresh()->isTemporarilyClosed());
        $this->assertFalse($venue->fresh()->is_active);
    }

    public function test_can_persist_venue_publication_revisions_and_link_to_venue(): void
    {
        $org = Organization::create([
            'name' => 'Hanoi Arena',
            'slug' => 'hanoi-arena',
            'status' => 'active',
        ]);

        $owner = User::factory()->create();
        $reviewer = User::factory()->create(['role' => 'admin']);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Hanoi Badminton Club',
            'slug' => 'hanoi-badminton-club',
            'address' => '12 Ba Trieu, Hanoi',
            'status' => 'pending_publication',
        ]);

        $revision = VenuePublicationRevision::create([
            'venue_id' => $venue->id,
            'revision_number' => 1,
            'status' => 'approved',
            'configuration_snapshot' => [
                'court_count' => 4,
                'pricing_model' => 'hourly_fixed',
                'operating_hours' => '06:00 - 22:00',
            ],
            'submitted_by' => $owner->id,
            'submitted_at' => now()->subDay(),
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'decision_reason' => 'Đầy đủ hồ sơ và mặt sân tiêu chuẩn.',
        ]);

        $venue->update([
            'status' => 'active',
            'current_publication_revision_id' => $revision->id,
            'published_at' => now(),
        ]);

        $this->assertEquals(1, $venue->publicationRevisions->count());
        $this->assertEquals($revision->id, $venue->currentPublicationRevision->id);
        $this->assertTrue($revision->isApproved());
        $this->assertEquals('Hanoi Badminton Club', $revision->venue->name);

        // Attempting to insert duplicate revision number for same venue must fail unique constraint
        $this->expectException(QueryException::class);

        VenuePublicationRevision::create([
            'venue_id' => $venue->id,
            'revision_number' => 1, // Duplicate
            'status' => 'pending',
            'configuration_snapshot' => ['dummy' => true],
            'submitted_by' => $owner->id,
            'submitted_at' => now(),
        ]);
    }
}
