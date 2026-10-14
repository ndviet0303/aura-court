<?php

namespace Tests\Feature\Infrastructure;

use App\Models\Organization;
use App\Services\Infrastructure\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagWildcardPilotTest extends TestCase
{
    use RefreshDatabase;

    public function test_wildcard_pilot_enables_direct_owner_payments_for_a_new_organization_but_not_payouts(): void
    {
        config([
            'features.pilot_organizations' => ['*'],
            'features.flags.direct_owner_payments' => true,
            'features.flags.marketplace_payouts' => false,
        ]);
        $org = Organization::create(['name' => 'Fresh Partner', 'slug' => 'fresh-'.uniqid()]);
        $flags = app(FeatureFlagService::class);

        $this->assertTrue($flags->enabled('direct_owner_payments', $org->id));
        $this->assertFalse($flags->enabled('marketplace_payouts', $org->id));
    }
}
