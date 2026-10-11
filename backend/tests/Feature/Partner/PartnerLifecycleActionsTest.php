<?php

namespace Tests\Feature\Partner;

use App\Domain\Partner\Actions\RestorePartner;
use App\Domain\Partner\Actions\SuspendPartner;
use App\Domain\Partner\Actions\TerminatePartner;
use App\Models\Organization;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PartnerLifecycleActionsTest extends TestCase
{
    use RefreshDatabase;

    private SuspendPartner $suspendAction;

    private RestorePartner $restoreAction;

    private TerminatePartner $terminateAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suspendAction = app(SuspendPartner::class);
        $this->restoreAction = app(RestorePartner::class);
        $this->terminateAction = app(TerminatePartner::class);
    }

    public function test_suspend_partner_sets_status_reason_and_audit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $org = Organization::create([
            'name' => 'Active Arena',
            'slug' => 'active-arena',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        $suspended = $this->suspendAction->execute(
            organizationId: $org->id,
            actorId: $admin->id,
            reason: 'Vi phạm chính sách an toàn vệ sinh sân bãi.'
        );

        $this->assertTrue($suspended->isSuspendedPartner());
        $this->assertEquals('Vi phạm chính sách an toàn vệ sinh sân bãi.', $suspended->partner_status_reason);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'organization.partner.suspended',
            'user_id' => $admin->id,
            'auditable_id' => $org->id,
        ]);
    }

    public function test_restore_partner_with_valid_subscription_enables_new_bookings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $org = Organization::create([
            'name' => 'Suspended Arena',
            'slug' => 'suspended-arena',
            'status' => 'active',
            'partner_status' => 'suspended',
            'partner_status_reason' => 'Đang tạm dừng',
        ]);

        $plan = PlatformPlan::create(['code' => 'p1', 'name' => 'Plan 1', 'price' => 100000]);
        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $result = $this->restoreAction->execute(
            organizationId: $org->id,
            actorId: $admin->id,
            notes: 'Đã hoàn thiện khắc phục vi phạm.'
        );

        $this->assertTrue($result['organization']->isApprovedPartner());
        $this->assertTrue($result['has_valid_entitlement']);
        $this->assertTrue($result['new_bookings_enabled']);
        $this->assertNull($result['organization']->partner_status_reason);
    }

    public function test_restore_partner_with_expired_subscription_restores_partner_without_enabling_bookings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $org = Organization::create([
            'name' => 'Expired Sub Arena',
            'slug' => 'expired-sub-arena',
            'status' => 'active',
            'partner_status' => 'suspended',
        ]);

        $plan = PlatformPlan::create(['code' => 'p2', 'name' => 'Plan 2', 'price' => 100000]);
        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'status' => 'expired', // Expired!
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
        ]);

        $result = $this->restoreAction->execute(
            organizationId: $org->id,
            actorId: $admin->id,
            notes: 'Đã giải quyết tranh chấp.'
        );

        // Partner status is restored to approved
        $this->assertTrue($result['organization']->isApprovedPartner());
        // But new bookings remain disabled due to expired plan!
        $this->assertFalse($result['has_valid_entitlement']);
        $this->assertFalse($result['new_bookings_enabled']);
    }

    public function test_cannot_restore_terminated_partner(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $org = Organization::create([
            'name' => 'Terminated Arena',
            'slug' => 'terminated-arena',
            'status' => 'active',
            'partner_status' => 'terminated',
            'partner_status_reason' => 'Vi phạm nghiêm trọng',
        ]);

        $this->expectException(HttpException::class);
        $this->restoreAction->execute($org->id, $admin->id);
    }

    public function test_terminate_partner_sets_terminal_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $org = Organization::create([
            'name' => 'To Terminate Arena',
            'slug' => 'to-terminate-arena',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        $terminated = $this->terminateAction->execute(
            organizationId: $org->id,
            actorId: $admin->id,
            reason: 'Chấm dứt hợp đồng hợp tác theo quyết định HĐQT.'
        );

        $this->assertTrue($terminated->isTerminatedPartner());
        $this->assertEquals('Chấm dứt hợp đồng hợp tác theo quyết định HĐQT.', $terminated->partner_status_reason);

        // Terminating again must fail with 409
        $this->expectException(HttpException::class);
        $this->terminateAction->execute($org->id, $admin->id, 'Second attempt');
    }
}
