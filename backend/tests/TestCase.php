<?php

namespace Tests;

use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSafeTestDatabase();
    }

    /**
     * Enforce strict isolation to prevent running destructive operations on non-test databases.
     */
    protected function ensureSafeTestDatabase(): void
    {
        if ($this->app->environment('production')) {
            throw new \RuntimeException('CRITICAL: Tests cannot run in production environment.');
        }

        $configuredDb = config('database.connections.pgsql.database');

        if ($configuredDb === 'booking' || ($configuredDb !== 'booking_test' && ! str_ends_with((string) $configuredDb, '_test'))) {
            throw new \RuntimeException("CRITICAL: Test attempted execution against non-test database: '{$configuredDb}'. Allowed: 'booking_test'.");
        }
    }

    /**
     * Helper to create an isolated multi-org fixture with sibling venues and courts.
     * Useful for cross-tenant and sibling-venue authorization testing.
     *
     * @return array{
     *     org1: array{org: Organization, owner: User, venue1: Venue, venue2: Venue, court1: Court, court2: Court},
     *     org2: array{org: Organization, owner: User, venue: Venue, court: Court}
     * }
     */
    protected function createMultiOrgVenueFixture(): array
    {
        // Tenant 1 with two sibling venues
        $org1 = Organization::create([
            'name' => 'Org 1 Primary',
            'slug' => 'org-1-'.uniqid(),
            'status' => 'active',
            'settings' => ['timezone' => 'Asia/Ho_Chi_Minh'],
        ]);

        $owner1 = User::factory()->create(['name' => 'Owner Org 1']);
        OrganizationMember::create([
            'organization_id' => $org1->id,
            'user_id' => $owner1->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);
        $venue1 = Venue::create([
            'organization_id' => $org1->id,
            'name' => 'Venue 1-A Primary',
            'slug' => 'venue-1a-'.uniqid(),
            'is_active' => true,
        ]);

        $court1 = Court::create([
            'venue_id' => $venue1->id,
            'name' => 'Court 1-A1',
            'is_active' => true,
        ]);

        $venue2 = Venue::create([
            'organization_id' => $org1->id,
            'name' => 'Venue 1-B Sibling',
            'slug' => 'venue-1b-'.uniqid(),
            'is_active' => true,
        ]);

        $court2 = Court::create([
            'venue_id' => $venue2->id,
            'name' => 'Court 1-B1',
            'is_active' => true,
        ]);

        // Tenant 2 (Foreign Org)
        $org2 = Organization::create([
            'name' => 'Org 2 Foreign',
            'slug' => 'org-2-'.uniqid(),
            'status' => 'active',
            'settings' => ['timezone' => 'Asia/Ho_Chi_Minh'],
        ]);

        $owner2 = User::factory()->create(['name' => 'Owner Org 2']);
        OrganizationMember::create([
            'organization_id' => $org2->id,
            'user_id' => $owner2->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);
        $venue3 = Venue::create([
            'organization_id' => $org2->id,
            'name' => 'Venue 2 Foreign',
            'slug' => 'venue-2-'.uniqid(),
            'is_active' => true,
        ]);

        $court3 = Court::create([
            'venue_id' => $venue3->id,
            'name' => 'Court 2-1',
            'is_active' => true,
        ]);

        return [
            'org1' => [
                'org' => $org1,
                'owner' => $owner1,
                'venue1' => $venue1,
                'venue2' => $venue2,
                'court1' => $court1,
                'court2' => $court2,
            ],
            'org2' => [
                'org' => $org2,
                'owner' => $owner2,
                'venue' => $venue3,
                'court' => $court3,
            ],
        ];
    }
}
