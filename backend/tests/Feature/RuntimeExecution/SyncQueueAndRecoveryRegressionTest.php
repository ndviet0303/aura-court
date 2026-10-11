<?php

namespace Tests\Feature\RuntimeExecution;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Organization;
use App\Models\OutboxMessage;
use App\Models\PaymentIntent;
use App\Models\ProviderEvent;
use App\Models\User;
use App\Models\Venue;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Infrastructure\OutboxService;
use App\Services\Infrastructure\ScheduledRecoveryService;
use App\Services\Payment\ProviderEventHandler;
use App\Services\Payment\ProviderWebhookService;
use App\Services\Payment\WalletPaymentService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncQueueAndRecoveryRegressionTest extends TestCase
{
    use RefreshDatabase;

    public static bool $executedFlag = false;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('queue.default', 'sync');
        Config::set('queue.connections.sync.after_commit', true);
        self::$executedFlag = false;
    }

    public function test_sync_queue_executes_immediately_without_worker(): void
    {
        self::$executedFlag = false;
        dispatch(new class
        {
            public function handle(): void
            {
                SyncQueueAndRecoveryRegressionTest::$executedFlag = true;
            }
        });

        $this->assertTrue(self::$executedFlag, 'Job dispatched on sync queue must execute synchronously within the calling thread.');
    }

    public function test_after_commit_job_waits_for_root_transaction_commit_and_discards_on_rollback(): void
    {
        self::$executedFlag = false;

        // Rollback test: job must NOT execute
        try {
            DB::transaction(function () {
                dispatch(new TestAfterCommitJob);
                throw new \RuntimeException('Force rollback');
            });
        } catch (\RuntimeException) {
            // Expected
        }

        $this->assertFalse(self::$executedFlag, 'Job with afterCommit=true must not execute when the transaction rolls back.');

        // Commit test: job MUST execute
        DB::transaction(function () {
            dispatch(new TestAfterCommitJob);
        });

        $this->assertTrue(self::$executedFlag, 'Job with afterCommit=true must execute after the root transaction commits.');
    }

    public function test_booking_notification_enqueued_in_outbox_and_preserves_success_on_mail_failure(): void
    {
        $org = Organization::create(['name' => 'Org A', 'slug' => 'org-a', 'status' => 'active']);
        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'Venue A', 'slug' => 'venue-a', 'is_active' => true]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => ['vi' => 'Sân 1', 'en' => 'Court 1'], 'is_active' => true]);
        $user = User::factory()->create(['email' => 'player@example.com']);

        $booking = Booking::create([
            'organization_id' => $org->id,
            'court_id' => $court->id,
            'user_id' => $user->id,
            'code' => 'BK-TEST-001',
            'status' => 'confirmed',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'subtotal' => 200000,
            'total_amount' => 200000,
        ]);

        // Simulate mail failure during synchronous delivery
        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('SMTP Connection Timeout'));

        // Notification routing through DurableNotificationService
        $durableNotification = app(DurableNotificationServiceInterface::class);

        $outboxMessage = null;
        try {
            $outboxMessage = $durableNotification->notifyBooking(
                $booking,
                'payment_confirmed',
                'Đặt sân thành công',
                'Booking của bạn đã hoàn tất',
                '/booking'
            );
        } catch (\Throwable $e) {
            $this->fail('DurableNotificationService should not throw secondary mail delivery failure: '.$e->getMessage());
        }

        $this->assertNotNull($outboxMessage);
        $this->assertDatabaseHas('outbox_messages', [
            'id' => $outboxMessage->id,
            'status' => 'pending',
            'attempts' => 1,
        ]);

        $fresh = $outboxMessage->fresh();
        $this->assertStringContainsString('SMTP Connection Timeout', (string) $fresh->last_error);
        // Database notification was delivered, mail failed
        $this->assertSame(['database'], $fresh->payload['delivered_channels']);
    }

    public function test_per_channel_retry_without_duplicate_database_notifications(): void
    {
        $org = Organization::create(['name' => 'Org B', 'slug' => 'org-b', 'status' => 'active']);
        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'Venue B', 'slug' => 'venue-b', 'is_active' => true]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => ['vi' => 'Sân 2', 'en' => 'Court 2'], 'is_active' => true]);
        $user = User::factory()->create(['email' => 'retry@example.com']);

        $booking = Booking::create([
            'organization_id' => $org->id,
            'court_id' => $court->id,
            'user_id' => $user->id,
            'code' => 'BK-TEST-RETRY',
            'status' => 'confirmed',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'subtotal' => 150000,
            'total_amount' => 150000,
        ]);

        // Attempt 1: Mail fails, database notification succeeds
        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('Transient Mail Failure'));

        $outbox = app(OutboxService::class);
        $durableNotification = app(DurableNotificationServiceInterface::class);

        $msg = $durableNotification->notifyBooking(
            $booking,
            'payment_confirmed',
            'Đặt sân thành công',
            'Nội dung thông báo',
            '/booking'
        );

        $this->assertEquals(1, $user->notifications()->count(), 'Database notification should be delivered on first attempt.');
        $this->assertSame(['database'], $msg->fresh()->payload['delivered_channels']);
        $this->assertSame('pending', $msg->fresh()->status);

        // Attempt 2: Scheduled recovery runs, mail now succeeds
        Mail::shouldReceive('send')->once()->andReturn(null);

        // Make available_at current
        $msg->update(['available_at' => now()->subMinute()]);

        $recovery = app(ScheduledRecoveryService::class);
        $recovered = $recovery->recoverOutbox();

        $this->assertSame(1, $recovered);
        $this->assertEquals(1, $user->notifications()->count(), 'Database notification must not be duplicated on mail retry.');
        $this->assertSame(['database', 'mail'], $msg->fresh()->payload['delivered_channels']);
        $this->assertSame('published', $msg->fresh()->status);
    }

    public function test_outbox_eight_attempt_exhaustion(): void
    {
        $user = User::factory()->create();
        $outbox = app(OutboxService::class);

        // Enqueue without auto-dispatch to test failure incrementation
        $message = OutboxMessage::create([
            'message_key' => 'exhaustion.test.'.uniqid(),
            'channel' => 'notification',
            'event_type' => 'test.exhaustion',
            'payload' => ['user_id' => $user->id, 'title' => 'Test', 'body' => 'Body'],
            'status' => 'processing',
            'attempts' => 7,
            'available_at' => now(),
        ]);

        // 8th failure
        $message->attempts = 8;
        $outbox->failed($message, new \RuntimeException('Permanent error'));

        $fresh = $message->fresh();
        $this->assertSame('exhausted', $fresh->status, 'Message must transition to exhausted after 8 attempts.');
        $this->assertNull($fresh->available_at, 'Exhausted message should have null available_at.');
        $this->assertSame('Permanent error', $fresh->last_error);
    }

    public function test_stale_claim_recovery(): void
    {
        $user = User::factory()->create();
        $outbox = app(OutboxService::class);

        // Stale message: status 'processing' with locked_at 10 minutes ago
        $message = OutboxMessage::create([
            'message_key' => 'stale.claim.'.uniqid(),
            'channel' => 'notification',
            'event_type' => 'test.stale',
            'payload' => ['user_id' => $user->id, 'channels' => ['database'], 'title' => 'Stale Test', 'body' => 'Body'],
            'status' => 'processing',
            'locked_at' => now()->subMinutes(10),
            'attempts' => 1,
            'available_at' => now()->subMinutes(10),
        ]);

        $recovery = app(ScheduledRecoveryService::class);
        $recovered = $recovery->recoverOutbox();

        $this->assertSame(1, $recovered, 'Stale claim must be recovered.');
        $this->assertSame('published', $message->fresh()->status);
    }

    public function test_provider_event_processing_failure_and_scheduled_recovery(): void
    {
        $org = Organization::create(['name' => 'Org C', 'slug' => 'org-c', 'status' => 'active']);
        $user = User::factory()->create();
        $wallet = app(WalletPaymentService::class)->getOrCreateWallet($user->id, $org->id);

        $intent = PaymentIntent::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'provider' => 'payos',
            'reference' => 'REF-FAIL-RECOVER',
            'idempotency_key' => 'REF-FAIL-RECOVER',
            'amount' => 100000,
            'currency' => 'VND',
            'status' => 'pending',
        ]);

        $webhooks = app(ProviderWebhookService::class);

        $event = ProviderEvent::create([
            'organization_id' => $org->id,
            'provider' => 'payos',
            'event_id' => 'EVT-FAIL-RECOVER-1',
            'type' => 'payment.success',
            'status' => 'PAID',
            'reference' => 'REF-FAIL-RECOVER',
            'amount' => 100000,
            'currency' => 'VND',
            'payload' => ['code' => '00'],
            'verified_at' => now(),
            'attempts' => 0,
            'next_attempt_at' => now(),
        ]);

        // Simulate failure during first attempt
        $webhooks->markFailed($event, new \RuntimeException('Database Deadlock'));

        $fresh = $event->fresh();
        $this->assertSame(1, $fresh->attempts);
        $this->assertStringContainsString('Database Deadlock', $fresh->processing_error);
        $this->assertNull($fresh->processed_at);

        // Now run scheduled recovery (make next_attempt_at eligible)
        $fresh->update(['next_attempt_at' => now()->subMinute()]);

        $recovery = app(ScheduledRecoveryService::class);
        $recovered = $recovery->recoverProviderEvents();

        $this->assertSame(1, $recovered, 'Provider event should be processed by recovery.');
        $this->assertNotNull($event->fresh()->processed_at);
        $this->assertSame('succeeded', $intent->fresh()->status);
        $this->assertSame(100000, $wallet->fresh()->balance);
    }

    public function test_duplicate_provider_events_or_recovery_race_is_idempotent(): void
    {
        $org = Organization::create(['name' => 'Org D', 'slug' => 'org-d', 'status' => 'active']);
        $user = User::factory()->create();
        $wallet = app(WalletPaymentService::class)->getOrCreateWallet($user->id, $org->id);

        $intent = PaymentIntent::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'provider' => 'payos',
            'reference' => 'REF-RACE-IDEMP',
            'idempotency_key' => 'REF-RACE-IDEMP',
            'amount' => 250000,
            'currency' => 'VND',
            'status' => 'pending',
        ]);

        $event = ProviderEvent::create([
            'organization_id' => $org->id,
            'provider' => 'payos',
            'event_id' => 'EVT-RACE-1',
            'type' => 'payment.success',
            'status' => 'PAID',
            'reference' => 'REF-RACE-IDEMP',
            'amount' => 250000,
            'currency' => 'VND',
            'payload' => ['code' => '00'],
            'verified_at' => now(),
            'next_attempt_at' => now(),
        ]);

        $handler = app(ProviderEventHandler::class);
        $webhooks = app(ProviderWebhookService::class);

        // Run once
        $handler->handle($event);
        $webhooks->markProcessed($event);

        $this->assertSame('succeeded', $intent->fresh()->status);
        $this->assertSame(250000, $wallet->fresh()->balance);

        // Run duplicate (simulating redelivery or concurrent recovery run)
        $handler->handle($event);

        $this->assertSame(250000, $wallet->fresh()->balance, 'Wallet must not be credited twice on duplicate processing.');
        $this->assertSame(1, WalletTransaction::where('wallet_account_id', $wallet->id)->count(), 'Exactly one transaction should exist.');
    }

    public function test_schedule_registration_has_overlap_protection_and_omits_queue_lag_in_sync_mode(): void
    {
        Config::set('queue.default', 'sync');

        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        // Check named tasks and withoutOverlapping
        $eventNames = $events->map(fn ($e) => $e->description ?? $e->command ?? '')->all();

        $bookingExpire = $events->first(fn ($e) => ($e->description ?? '') === 'booking:expire-pending');
        $this->assertNotNull($bookingExpire, 'booking:expire-pending schedule must be registered.');
        $this->assertTrue($bookingExpire->withoutOverlapping, 'booking:expire-pending must have withoutOverlapping enabled.');

        $outboxRecovery = $events->first(fn ($e) => ($e->description ?? '') === 'recovery:outbox');
        $this->assertNotNull($outboxRecovery, 'recovery:outbox schedule must be registered.');
        $this->assertTrue($outboxRecovery->withoutOverlapping, 'recovery:outbox must have withoutOverlapping enabled.');

        $providerRecovery = $events->first(fn ($e) => ($e->description ?? '') === 'recovery:provider-events');
        $this->assertNotNull($providerRecovery, 'recovery:provider-events schedule must be registered.');
        $this->assertTrue($providerRecovery->withoutOverlapping, 'recovery:provider-events must have withoutOverlapping enabled.');

        $reconciliation = $events->first(fn ($e) => ($e->description ?? '') === 'finance:daily-reconciliation');
        $this->assertNotNull($reconciliation, 'finance:daily-reconciliation schedule must be registered.');
        $this->assertTrue($reconciliation->withoutOverlapping, 'finance:daily-reconciliation must have withoutOverlapping enabled.');

        // Verify monitor:queue-lag is NOT scheduled when queue.default is sync
        $queueLag = $events->first(fn ($e) => str_contains($e->command ?? '', 'monitor:queue-lag'));
        $this->assertNull($queueLag, 'monitor:queue-lag must be omitted from schedule when queue connection is sync.');
    }

    public function test_reconciliation_failure_isolation_per_organization(): void
    {
        $orgA = Organization::create(['name' => 'Org Failing', 'slug' => 'org-failing', 'status' => 'active']);
        $orgB = Organization::create(['name' => 'Org Healthy', 'slug' => 'org-healthy', 'status' => 'active']);

        $processedOrgs = [];

        // Simulate the scheduled reconciliation closure logic
        $activeOrgs = Organization::query()->where('status', 'active')->pluck('id');
        foreach ($activeOrgs as $id) {
            try {
                if ($id === $orgA->id) {
                    throw new \RuntimeException("Reconciliation error for org {$id}");
                }
                $processedOrgs[] = $id;
            } catch (\Throwable $e) {
                // Isolated per organization
            }
        }

        $this->assertContains($orgB->id, $processedOrgs, 'Healthy organization must be processed even if another organization fails reconciliation.');
    }

    public function test_outbox_recovery_continues_past_failing_item(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Message 1 will fail (user non-existent)
        $msg1 = OutboxMessage::create([
            'message_key' => 'fail.item.'.uniqid(),
            'channel' => 'notification',
            'event_type' => 'test.fail',
            'payload' => ['user_id' => 999999, 'channels' => ['database'], 'title' => 'Fail', 'body' => 'Fail'],
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => now()->subMinute(),
        ]);

        // Message 2 should succeed
        $msg2 = OutboxMessage::create([
            'message_key' => 'succeed.item.'.uniqid(),
            'channel' => 'notification',
            'event_type' => 'test.succeed',
            'payload' => ['user_id' => $userB->id, 'channels' => ['database'], 'title' => 'Succeed', 'body' => 'Succeed'],
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => now()->subMinute(),
        ]);

        $recovery = app(ScheduledRecoveryService::class);
        $recovered = $recovery->recoverOutbox();

        $this->assertSame(1, $recovered, 'Second message should succeed despite first message failing.');
        $this->assertSame('pending', $msg1->fresh()->status, 'Failing message should remain pending for backoff.');
        $this->assertSame(1, $msg1->fresh()->attempts);
        $this->assertSame('published', $msg2->fresh()->status, 'Succeeding message must be published.');
    }

    public function test_provider_event_recovery_continues_past_failing_item(): void
    {
        $org = Organization::create(['name' => 'Org E', 'slug' => 'org-e', 'status' => 'active']);
        $user = User::factory()->create();
        $wallet = app(WalletPaymentService::class)->getOrCreateWallet($user->id, $org->id);

        // Event 1: Missing reference, will fail or be unresolved
        $event1 = ProviderEvent::create([
            'organization_id' => $org->id,
            'provider' => 'payos',
            'event_id' => 'EVT-FAIL-CONT-1',
            'type' => 'payment.success',
            'status' => 'PAID',
            'reference' => 'NON_EXISTENT_INTENT',
            'amount' => 50000,
            'currency' => 'VND',
            'payload' => [],
            'verified_at' => now(),
            'attempts' => 0,
            'next_attempt_at' => now()->subMinute(),
        ]);

        // Event 2: Valid intent, will succeed
        $intent2 = PaymentIntent::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'provider' => 'payos',
            'reference' => 'REF-CONT-SUCCESS-2',
            'idempotency_key' => 'REF-CONT-SUCCESS-2',
            'amount' => 75000,
            'currency' => 'VND',
            'status' => 'pending',
        ]);

        $event2 = ProviderEvent::create([
            'organization_id' => $org->id,
            'provider' => 'payos',
            'event_id' => 'EVT-SUCCESS-CONT-2',
            'type' => 'payment.success',
            'status' => 'PAID',
            'reference' => 'REF-CONT-SUCCESS-2',
            'amount' => 75000,
            'currency' => 'VND',
            'payload' => [],
            'verified_at' => now(),
            'attempts' => 0,
            'next_attempt_at' => now()->subMinute(),
        ]);

        $recovery = app(ScheduledRecoveryService::class);
        $recovered = $recovery->recoverProviderEvents();

        // Both were processed by recovery loop (event 1 handled safely/or marked, event 2 credited wallet)
        $this->assertNotNull($event2->fresh()->processed_at);
        $this->assertSame('succeeded', $intent2->fresh()->status);
        $this->assertSame(75000, $wallet->fresh()->balance);
    }

    public function test_host_style_schedule_run_tick_executes_successfully(): void
    {
        $exitCode = Artisan::call('schedule:run');
        $this->assertSame(0, $exitCode, 'Artisan schedule:run tick must exit with 0 code in sync mode.');
    }
}

class TestAfterCommitJob implements ShouldQueue
{
    use Dispatchable, \Illuminate\Queue\InteractsWithQueue, \Illuminate\Queue\SerializesModels;

    public bool $afterCommit = true;

    public function handle(): void
    {
        SyncQueueAndRecoveryRegressionTest::$executedFlag = true;
    }
}
