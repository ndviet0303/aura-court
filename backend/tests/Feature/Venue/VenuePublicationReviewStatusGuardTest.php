<?php

namespace Tests\Feature\Venue;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePublicationRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VenuePublicationReviewStatusGuardTest extends TestCase
{
    use RefreshDatabase;

    private function createPublishedVenue(): array
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $org = Organization::create([
            'name' => 'Review Guard Org',
            'slug' => 'review-guard-org',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => 'organization_owner',
            'status' => 'active',
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'San Review Guard',
            'slug' => 'san-review-guard',
            'address' => '100 Nguyen Van Linh, Da Nang',
            'status' => 'published',
            'is_active' => true,
            'approved_public_snapshot' => ['venue' => ['name' => 'San Review Guard']],
        ]);

        return [$owner, $admin, $org, $venue];
    }

    private function pendingRevision(Venue $venue, User $owner): VenuePublicationRevision
    {
        return VenuePublicationRevision::create([
            'venue_id' => $venue->id,
            'revision_number' => (int) $venue->publicationRevisions()->max('revision_number') + 1,
            'status' => 'pending',
            'configuration_snapshot' => ['venue' => ['name' => 'San Review Guard (sua)']],
            'submitted_by' => $owner->id,
            'submitted_at' => now(),
        ]);
    }

    public function test_review_of_a_stale_revision_never_lifts_a_suspension_or_reopens_a_closure(): void
    {
        [$owner, $admin, , $venue] = $this->createPublishedVenue();

        Sanctum::actingAs($admin);
        foreach (['suspended', 'closed', 'archived'] as $status) {
            $venue->update(['status' => $status, 'is_active' => false]);

            $approve = $this->pendingRevision($venue, $owner);
            $this->postJson("/api/admin/venues/revisions/{$approve->id}/review", ['decision' => 'approve'])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['status']);
            $this->assertSame('pending', $approve->fresh()->status);
            $this->assertSame($status, $venue->fresh()->status);

            // Correcting or rejecting still settles the stale revision, without touching the venue status
            foreach (['revision_required' => 'revision_required', 'reject' => 'rejected'] as $decision => $settled) {
                $revision = $this->pendingRevision($venue, $owner);
                $this->postJson("/api/admin/venues/revisions/{$revision->id}/review", [
                    'decision' => $decision,
                    'reason' => 'Bản sửa đổi đã cũ so với trạng thái cơ sở.',
                ])->assertOk();
                $this->assertSame($settled, $revision->fresh()->status);
                $this->assertSame($status, $venue->fresh()->status);
                $this->assertFalse($venue->fresh()->is_active);
            }
        }
    }

    public function test_owner_cannot_reopen_a_closed_venue_through_a_revision_filed_before_closing(): void
    {
        [$owner, $admin, $org, $venue] = $this->createPublishedVenue();
        $revision = $this->pendingRevision($venue, $owner);
        $lifecycleUrl = "/api/partner/organizations/{$org->id}/venues/{$venue->id}/lifecycle";

        Sanctum::actingAs($owner);
        $this->postJson($lifecycleUrl, ['status' => 'closed'])->assertOk();

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/venues/revisions/{$revision->id}/review", [
            'decision' => 'reject',
            'reason' => 'Cơ sở đã ngừng kinh doanh.',
        ])->assertOk();
        $this->assertSame('closed', $venue->fresh()->status);

        Sanctum::actingAs($owner);
        foreach (['temporarily_closed', 'published'] as $target) {
            $this->postJson($lifecycleUrl, ['status' => $target])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['status']);
        }
        $this->assertSame('closed', $venue->fresh()->status);
    }
}
