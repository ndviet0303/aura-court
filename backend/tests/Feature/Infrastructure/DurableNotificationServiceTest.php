<?php

namespace Tests\Feature\Infrastructure;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Models\HostKyc;
use App\Models\Organization;
use App\Models\OutboxMessage;
use App\Models\PlatformPaymentAttempt;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePublicationRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DurableNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private DurableNotificationServiceInterface $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = app(DurableNotificationServiceInterface::class);
    }

    public function test_enqueues_application_submitted_notification_with_deduplication(): void
    {
        $user = User::factory()->create();

        $kyc = HostKyc::create([
            'kyc_code' => 'KYC-NOTIF-01',
            'user_id' => $user->id,
            'business_type' => 'individual',
            'business_name' => 'Court 1',
            'legal_name' => 'User 1',
            'tax_code' => '1122334455',
            'representative_name' => 'User 1',
            'id_card_number' => '123456789012',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '123',
            'bank_account_holder' => 'USER 1',
            'status' => 'pending',
            'version' => 1,
        ]);

        $msg1 = $this->notificationService->notifyApplicationSubmitted($kyc);
        $this->assertEquals("kyc.submitted.{$kyc->id}.v1", $msg1->message_key);
        $this->assertEquals('notification', $msg1->channel);
        $this->assertEquals('pending', $msg1->status);

        // Deduplication test: second call with same KYC state must return identical message
        $msg2 = $this->notificationService->notifyApplicationSubmitted($kyc);
        $this->assertEquals($msg1->id, $msg2->id);
        $this->assertEquals(1, OutboxMessage::where('message_key', "kyc.submitted.{$kyc->id}.v1")->count());
    }

    public function test_application_reviewed_subscription_payment_and_venue_notifications(): void
    {
        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Notif Org', 'slug' => 'notif-org', 'status' => 'active']);
        \App\Models\OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'organization_owner',
            'status' => 'active',
        ]);

        $kyc = HostKyc::create([
            'kyc_code' => 'KYC-NOTIF-02',
            'user_id' => $user->id,
            'business_type' => 'individual',
            'business_name' => 'Court 2',
            'legal_name' => 'User 2',
            'tax_code' => '1122334456',
            'representative_name' => 'User 2',
            'id_card_number' => '123456789013',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '124',
            'bank_account_holder' => 'USER 2',
            'status' => 'approved',
            'version' => 2,
        ]);

        $revMsg = $this->notificationService->notifyApplicationReviewed($kyc, 'approve');
        $this->assertEquals("kyc.reviewed.{$kyc->id}.v2.approve", $revMsg->message_key);

        $plan = PlatformPlan::create(['code' => 'p1', 'name' => 'Plan 1', 'price' => 100000]);
        $sub = PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'status' => 'active',
        ]);
        $subMsg = $this->notificationService->notifySubscriptionActivated($sub);
        $this->assertEquals("subscription.activated.{$sub->id}.v1", $subMsg->message_key);

        $attempt = PlatformPaymentAttempt::create([
            'subscription_id' => $sub->id,
            'organization_id' => $org->id,
            'reference_code' => 'PAY-FAIL-99',
            'amount' => 100000,
            'status' => 'failed',
            'failure_reason' => 'Tài khoản không đủ số dư',
        ]);
        $payFailMsg = $this->notificationService->notifyPaymentFailed($attempt);
        $this->assertEquals('payment.failed.PAY-FAIL-99', $payFailMsg->message_key);

        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'V1', 'slug' => 'v1', 'address' => 'HCMC']);
        $revision = VenuePublicationRevision::create([
            'venue_id' => $venue->id,
            'revision_number' => 1,
            'status' => 'approved',
            'configuration_snapshot' => [],
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ]);
        $venuePubMsg = $this->notificationService->notifyPublicationReviewed($revision, 'approved');
        $this->assertEquals("venue.publication.{$revision->id}.approved", $venuePubMsg->message_key);
    }

    public function test_can_record_audit_log(): void
    {
        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Audit Org', 'slug' => 'audit-org', 'status' => 'active']);

        $log = $this->notificationService->recordAudit(
            userId: $user->id,
            action: 'organization.partner_status.updated',
            auditable: $org,
            metadata: ['old_status' => 'pending', 'new_status' => 'approved']
        );

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'user_id' => $user->id,
            'action' => 'organization.partner_status.updated',
            'auditable_type' => Organization::class,
            'auditable_id' => $org->id,
        ]);
    }
}
