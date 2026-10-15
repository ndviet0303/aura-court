<?php

namespace Tests\Feature;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Contracts\Payment\PayoutStatusResponse;
use App\Contracts\Payment\PayoutSubmissionResponse;
use App\Models\Beneficiary;
use App\Models\FinanceException;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Payout;
use App\Models\Settlement;
use App\Models\User;
use App\Services\Payment\PaymentProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FinanceReportsSettlementPayoutExceptionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $owner;

    private User $manager;

    private User $cashier;

    private User $staff;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Finance Pilot Org',
            'slug' => 'fin-pilot-'.uniqid(),
            'status' => 'active',
        ]);

        config([
            'features.flags.marketplace_payouts' => true,
            'features.pilot_organizations' => [(string) $this->organization->id],
            'finance.minimum_payout' => 500000,
            'services.payments.default_provider' => 'payos',
        ]);

        $this->owner = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
        ]);

        $this->manager = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->manager->id,
            'role' => OrganizationMember::ROLE_STAFF,
        ]);

        $this->cashier = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->cashier->id,
            'role' => OrganizationMember::ROLE_STAFF,
        ]);

        $this->staff = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->staff->id,
            'role' => OrganizationMember::ROLE_STAFF,
        ]);

        $this->customer = User::factory()->create();
    }

    public function test_role_matrix_permits_owner_but_blocks_staff_and_customer(): void
    {
        $headers = ['X-Organization-Id' => $this->organization->id];

        // Owner can access
        $this->actingAs($this->owner)
            ->getJson('/api/finance/settlements', $headers)
            ->assertOk();

        // BR-21: payouts stay with the owner; former venue managers are staff
        $this->actingAs($this->manager)
            ->getJson('/api/finance/settlements', $headers)
            ->assertStatus(403);

        // Cashier is forbidden
        $this->actingAs($this->cashier)
            ->getJson('/api/finance/settlements', $headers)
            ->assertStatus(403);

        // Staff is forbidden
        $this->actingAs($this->staff)
            ->getJson('/api/finance/settlements', $headers)
            ->assertStatus(403);

        // Unaffiliated customer is forbidden
        $this->actingAs($this->customer)
            ->getJson('/api/finance/settlements', $headers)
            ->assertStatus(403);
    }

    public function test_feature_flag_disabled_blocks_access_to_finance_endpoints(): void
    {
        config(['features.flags.marketplace_payouts' => false]);
        $headers = ['X-Organization-Id' => $this->organization->id];

        $this->actingAs($this->owner)
            ->getJson('/api/finance/settlements', $headers)
            ->assertStatus(404);

        config(['features.flags.marketplace_payouts' => true]);
    }

    public function test_csv_exports_return_csv_content_and_headers(): void
    {
        $headers = ['X-Organization-Id' => $this->organization->id];

        // Seed settlement, payout, exception
        $settlement = Settlement::create([
            'organization_id' => $this->organization->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-07',
            'gross_amount' => 10000000,
            'refund_amount' => 500000,
            'provider_fee_amount' => 0,
            'commission_amount' => 500000,
            'tenant_payable' => 9000000,
            'currency' => 'VND',
            'status' => 'eligible',
            'eligible_at' => now()->addDays(2),
        ]);

        $beneficiary = Beneficiary::create([
            'organization_id' => $this->organization->id,
            'provider' => 'payos',
            'bank_code' => 'TPB',
            'account_number' => '1234567890',
            'account_holder' => 'CONG TY TNHH AURA',
            'status' => 'verified',
        ]);

        Payout::create([
            'organization_id' => $this->organization->id,
            'settlement_id' => $settlement->id,
            'beneficiary_id' => $beneficiary->id,
            'idempotency_key' => 'payout-test-csv',
            'amount' => 9000000,
            'currency' => 'VND',
            'status' => 'draft',
            'settlement_snapshot' => ['tenant_payable' => 9000000],
        ]);

        FinanceException::create([
            'organization_id' => $this->organization->id,
            'type' => 'reconciliation_mismatch',
            'reference' => 'reconciliation:2026-09-07',
            'severity' => 'high',
            'amount' => 100000,
            'description' => 'Discrepancy detected during test',
            'status' => 'open',
            'detected_at' => now(),
            'due_at' => now()->addHours(2),
        ]);

        // Settlements CSV
        $settlementsCsv = $this->actingAs($this->owner)
            ->get('/api/finance/settlements.csv', $headers);
        $settlementsCsv->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Period Start', $settlementsCsv->streamedContent());
        $this->assertStringContainsString('2026-09-01', $settlementsCsv->streamedContent());

        // Payouts CSV
        $payoutsCsv = $this->actingAs($this->owner)
            ->get('/api/finance/payouts.csv', $headers);
        $payoutsCsv->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Beneficiary', $payoutsCsv->streamedContent());
        $this->assertStringContainsString('CONG TY TNHH AURA', $payoutsCsv->streamedContent());

        // Exceptions CSV
        $exceptionsCsv = $this->actingAs($this->owner)
            ->get('/api/finance/exceptions.csv', $headers);
        $exceptionsCsv->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('reconciliation_mismatch', $exceptionsCsv->streamedContent());
    }

    public function test_settlements_snapshot_and_listing_payloads(): void
    {
        $headers = [
            'X-Organization-Id' => $this->organization->id,
            'Idempotency-Key' => 'snap-'.uniqid(),
        ];

        $res = $this->actingAs($this->owner)->postJson('/api/finance/settlements/snapshot', [
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-05',
        ], $headers);

        $res->assertCreated()
            ->assertJsonPath('settlement.currency', 'VND');
        $this->assertNotNull($res->json('settlement.id'));
        $list = $this->actingAs($this->owner)->getJson('/api/finance/settlements', $headers);
        $list->assertOk()
            ->assertJsonStructure(['data' => [['id', 'period_start', 'period_end', 'status', 'gross_amount']]]);
    }

    public function test_payout_workflow_from_draft_to_approve_submit_and_status(): void
    {
        $headers = ['X-Organization-Id' => $this->organization->id];

        $settlement = Settlement::create([
            'organization_id' => $this->organization->id,
            'period_start' => '2026-08-20',
            'period_end' => '2026-08-27',
            'gross_amount' => 5000000,
            'tenant_payable' => 4500000,
            'currency' => 'VND',
            'status' => 'eligible',
            'eligible_at' => now()->subDay(),
            'snapshotted_at' => now()->subDays(2),
            'snapshot' => ['tenant_payable' => 4500000],
        ]);

        $beneficiary = Beneficiary::create([
            'organization_id' => $this->organization->id,
            'provider' => 'payos',
            'bank_code' => '970415',
            'account_number' => '1029384756',
            'account_holder' => 'AURA TEST ACCOUNT',
            'provider_beneficiary_id' => 'ben-provider-123',
            'status' => 'verified',
        ]);

        // 1. Create Draft Payout
        $createRes = $this->actingAs($this->owner)->postJson('/api/finance/payouts', [
            'settlement_id' => $settlement->id,
            'beneficiary_id' => $beneficiary->id,
        ], array_merge($headers, ['Idempotency-Key' => 'pay-draft-'.uniqid()]));

        $createRes->assertCreated()
            ->assertJsonPath('payout.status', 'draft')
            ->assertJsonPath('payout.amount', 4500000);

        $payoutId = $createRes->json('payout.id');

        // 2. Approve Payout
        $approveRes = $this->actingAs($this->owner)->postJson("/api/finance/payouts/{$payoutId}/approve", [], array_merge($headers, ['Idempotency-Key' => 'pay-appr-'.uniqid()]));
        $approveRes->assertOk()
            ->assertJsonPath('payout.status', 'approved');

        // Mock payment provider for payout submission and status query
        $mockProvider = Mockery::mock(PaymentProviderInterface::class);
        $mockProvider->shouldReceive('name')->andReturn('payos');
        $mockProvider->shouldReceive('submitPayout')->once()->andReturn(new PayoutSubmissionResponse(
            provider: 'payos',
            providerPayoutId: 'payout-sub-999',
            status: 'SUBMITTED',
            amount: 4500000,
            currency: 'VND',
            raw: ['id' => 'payout-sub-999'],
        ));
        $mockProvider->shouldReceive('queryPayoutStatus')->once()->andReturn(new PayoutStatusResponse(
            provider: 'payos',
            providerPayoutId: 'payout-sub-999',
            status: 'PAID',
            raw: ['status' => 'PAID'],
        ));

        $providerManager = new PaymentProviderManager($mockProvider);
        $this->app->instance(PaymentProviderManager::class, $providerManager);

        // 3. Submit Payout
        $submitRes = $this->actingAs($this->owner)->postJson("/api/finance/payouts/{$payoutId}/submit", [], array_merge($headers, ['Idempotency-Key' => 'pay-sub-'.uniqid()]));
        $submitRes->assertOk()
            ->assertJsonPath('payout.status', 'submitted')
            ->assertJsonPath('payout.provider_payout_id', 'payout-sub-999');

        // 4. Query Payout Status
        $statusRes = $this->actingAs($this->owner)->postJson("/api/finance/payouts/{$payoutId}/status", [], array_merge($headers, ['Idempotency-Key' => 'pay-stat-'.uniqid()]));
        $statusRes->assertOk()
            ->assertJsonPath('payout.status', 'paid');
    }

    public function test_exceptions_list_stats_and_resolution(): void
    {
        $headers = ['X-Organization-Id' => $this->organization->id];

        $exception = FinanceException::create([
            'organization_id' => $this->organization->id,
            'type' => 'discrepancy',
            'reference' => 'DISC-001',
            'severity' => 'medium',
            'amount' => 50000,
            'description' => 'Voucher difference',
            'status' => 'open',
            'detected_at' => now(),
            'due_at' => now()->addDay(),
        ]);

        // Stats
        $statsRes = $this->actingAs($this->owner)->getJson('/api/finance/exceptions/stats', $headers);
        $statsRes->assertOk()
            ->assertJsonStructure(['stats' => ['pending_count', 'critical_count']]);
        // List
        $listRes = $this->actingAs($this->owner)->getJson('/api/finance/exceptions', $headers);
        $listRes->assertOk()
            ->assertJsonStructure(['data' => [['id', 'type', 'severity', 'status']]]);

        // Resolve
        $resolveRes = $this->actingAs($this->owner)->postJson("/api/finance/exceptions/{$exception->id}/resolve", [
            'action_type' => 'manual_override',
            'reason' => 'Xác nhận do điều chỉnh giá giờ vàng hợp lệ',
            'note' => 'Đã ghi chú vào hồ sơ kiểm toán',
        ], array_merge($headers, ['Idempotency-Key' => 'ex-res-'.uniqid()]));
        $resolveRes->assertOk()
            ->assertJsonPath('exception.status', 'resolved');

        $this->assertSame('resolved', $exception->fresh()->status);
    }

    public function test_reconciliation_stats_and_runs_endpoints(): void
    {
        $headers = ['X-Organization-Id' => $this->organization->id];

        $stats = $this->actingAs($this->owner)->getJson('/api/finance/reconciliation/stats', $headers);
        // An organization with no finance activity reports zeros and nulls, never placeholder figures.
        $stats->assertOk()
            ->assertJsonStructure(['provider_total', 'journal_total', 'settlement_total', 'payout_total', 'sync_status'])
            ->assertJsonPath('provider_total', 0)
            ->assertJsonPath('journal_total', 0)
            ->assertJsonPath('settlement_total', 0)
            ->assertJsonPath('payout_total', 0)
            ->assertJsonPath('mismatch_total', 0)
            ->assertJsonPath('open_alerts_count', 0)
            ->assertJsonPath('match_rate', null)
            ->assertJsonPath('last_reconciled_at', null)
            ->assertJsonCount(7, 'health_trend')
            ->assertJsonPath('health_trend.6.status', null);
        $runs = $this->actingAs($this->owner)->getJson('/api/finance/reconciliation/runs', $headers);
        $runs->assertOk()
            ->assertJsonStructure(['data']);
    }
}
