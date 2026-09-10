<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Organization;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcileLegacyPartnersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_reports_legacy_records_without_mutating_data(): void
    {
        Booking::query()->delete();
        Venue::query()->delete();
        Organization::query()->delete();
        $owner = User::factory()->create();
        $org = Organization::create([
            'name' => 'Legacy Club',
            'slug' => 'legacy-club',
            'partner_status' => 'pending_verification',
        ]);
        $org->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Legacy Venue',
            'slug' => 'legacy-venue',
            'address' => '123 Legacy St',
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = Court::create([
            'venue_id' => $venue->id,
            'name' => 'Court 1',
            'court_type' => 'indoor',
            'surface_type' => 'wood',
            'is_active' => true,
        ]);

        $customer = User::factory()->create();
        $booking = Booking::create([
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'code' => 'LEGACY-BK-001',
            'total_amount' => 100000,
            'status' => 'confirmed',
        ]);

        $orgCountBefore = Organization::count();
        $venueCountBefore = Venue::count();
        $bookingCountBefore = Booking::count();
        $this->assertSame(1, $orgCountBefore);
        $this->artisan('partner:reconcile-legacy')
            ->assertSuccessful()
            ->expectsOutputToContain('Starting read-only legacy partner and venue reconciliation audit...')
            ->expectsOutputToContain('Missing Approved Application: 1')
            ->expectsOutputToContain('Missing Platform Subscription: 1')
            ->expectsOutputToContain('Venues Missing Approved Publication Revision: 1')
            ->expectsOutputToContain('Outstanding Future Bookings to Protect: 1');

        // Verify zero mutations
        $this->assertSame($orgCountBefore, Organization::count());
        $this->assertSame($venueCountBefore, Venue::count());
        $this->assertSame($bookingCountBefore, Booking::count());

        $this->assertSame('pending_verification', $org->fresh()->partner_status);
        $this->assertTrue((bool) $venue->fresh()->is_active);
        $this->assertSame('confirmed', $booking->fresh()->status);
    }
}
