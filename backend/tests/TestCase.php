<?php

namespace Tests;

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

    /** Destructive tests run only against a database whose name ends in _test. */
    protected function ensureSafeTestDatabase(): void
    {
        if ($this->app->environment('production')) {
            throw new \RuntimeException('CRITICAL: Tests cannot run in production environment.');
        }

        $configuredDb = (string) config('database.connections.pgsql.database');
        if ($configuredDb === 'booking' || ! str_ends_with($configuredDb, '_test')) {
            throw new \RuntimeException("CRITICAL: Test attempted execution against non-test database: '{$configuredDb}'.");
        }
    }

    /** A new active owner of the organization. */
    protected function ownerOf(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationMember::ROLE_ORGANIZATION_OWNER);
    }

    /** Active staff of the venue's organization, assigned to these venues (BR-20). */
    protected function staffOf(Venue $venue, Venue ...$more): User
    {
        $ids = array_map(fn (Venue $v) => $v->id, [$venue, ...$more]);

        return $this->memberOf($venue->organization, OrganizationMember::ROLE_STAFF, ['venue_id' => $ids[0], 'venue_ids' => $ids]);
    }

    protected function platformAdmin(): User
    {
        return User::factory()->create(['role' => 'platform_admin']);
    }

    /** @param  array<string, mixed>|null  $permissions */
    private function memberOf(Organization $organization, string $role, ?array $permissions = null): User
    {
        $user = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'permissions' => $permissions,
        ]);

        return $user;
    }
}
