<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Partner;

use App\Domain\Partner\Services\PartnerRolloutService;
use App\Models\Organization;
use App\Models\Venue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PartnerRolloutServiceTest extends TestCase
{
    private PartnerRolloutService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PartnerRolloutService();
    }

    public function test_should_enforce_checks_cohorts_and_aspects(): void
    {
        // 1. Wildcard cohort
        Config::set('partner.enforce_cohorts', ['*']);
        Config::set('partner.enforce_v2_registration', true);
        Config::set('partner.enforce_publication_check', false);

        $this->assertTrue($this->service->shouldEnforce(10, 'registration'));
        $this->assertFalse($this->service->shouldEnforce(10, 'publication'));

        // 2. Specific cohort
        Config::set('partner.enforce_cohorts', ['10', '20']);
        $this->assertTrue($this->service->shouldEnforce(10, 'registration'));
        $this->assertFalse($this->service->shouldEnforce(30, 'registration'));

        // 3. Organization instance
        $org = new Organization();
        $org->id = 20;
        $this->assertTrue($this->service->shouldEnforce($org, 'registration'));
    }

    public function test_record_shadow_comparison_logs_discrepancy(): void
    {
        Log::spy();
        Config::set('partner.shadow_mode', true);

        $venue = new Venue();
        $venue->id = 55;
        $venue->organization_id = 12;

        // Discrepancy: legacy allowed, but v2 blocked
        $this->service->recordShadowComparison($venue, 'booking_creation', true, false, ['court_id' => 1]);

        Log::shouldHaveReceived('warning')->with(
            'partner_rollout.shadow_discrepancy',
            \Mockery::on(function ($context) {
                return $context['venue_id'] === 55
                    && $context['legacy_allowed'] === true
                    && $context['v2_allowed'] === false;
            })
        );
    }

    public function test_record_shadow_comparison_does_not_log_discrepancy_when_shadow_mode_disabled(): void
    {
        Log::spy();
        Config::set('partner.shadow_mode', false);

        $venue = new Venue();
        $venue->id = 55;

        $this->service->recordShadowComparison($venue, 'booking_creation', true, false);

        Log::shouldNotHaveReceived('warning');
    }

    public function test_rollback_safe_invariant(): void
    {
        $this->assertTrue($this->service->isRollbackSafe());
    }
}
