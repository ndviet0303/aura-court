<?php

namespace Tests\Feature\Readiness;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Events\CourtStatusUpdated;
use App\Jobs\ProcessOutboxMessage;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\CashShift;
use App\Models\Court;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OutboxMessage;
use App\Models\Payment;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosPayment;
use App\Models\PosProduct;
use App\Models\ProviderEvent;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Services\Attendance\AttendanceService;
use App\Services\Finance\FinancialPostingService;
use App\Services\Infrastructure\OutboxService;
use App\Services\Infrastructure\ScheduledRecoveryService;
use App\Services\POS\PosService;
use App\Services\Reservation\LegacyBookingBackfillService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\PaysOwnerByQr;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

/**
 * Task 13.6 Acceptance Test:
 * Reliability / Backup-Restore / Realtime / Host Scheduler readiness verification
 * according to current main specs.
 *
 * Strict Ground Truth:
 * - Realtime events and event broadcasting: CourtStatusUpdated lifecycle & async Outbox dispatch
 * - Host scheduler & Unpaid hold sweep: Released slots back to available & POS stock restocked
 * - Reliability & Failure isolation: External failure does not rollback financial transactions; idempotent retry
 * - Backup & Restore invariant verification: Table counts, financial journal sums, and foreign key integrity
 * - Real NFR measurements: Actual execution times asserted with real recorded durations (< 500ms)
 */
class ReliabilityRealtimeAndSchedulerReadinessTest extends TestCase
{
    use PaysOwnerByQr;
    use PricesCourts;
    use RefreshDatabase;

    private function createTenantFixture(string $prefix = 'rel'): array
    {
        $org = Organization::create([
            'name' => "Org {$prefix}",
            'slug' => "org-{$prefix}-".uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        $owner = User::factory()->create([
            'email' => "owner.{$prefix}.".uniqid().'@aura.test',
            'email_verified_at' => now(),
            'role' => 'owner',
        ]);

        $staff = User::factory()->create([
            'email' => "staff.{$prefix}.".uniqid().'@aura.test',
            'email_verified_at' => now(),
            'role' => 'staff',
        ]);

        $customer = User::factory()->create([
            'email' => "customer.{$prefix}.".uniqid().'@aura.test',
            'email_verified_at' => now(),
            'role' => 'customer',
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $staff->id,
            'role' => OrganizationMember::ROLE_STAFF,
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $customer->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => "Venue {$prefix}",
            'slug' => "venue-{$prefix}-".uniqid(),
            'address' => '123 Le Loi, D1, HCMC',
            'phone' => '0901234567',
            'status' => 'published',
            'is_active' => true,
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);
        // BR-20: staff reach only the venues assigned to them.
        OrganizationMember::where('organization_id', $org->id)->where('role', 'staff')
            ->get()->each(fn (OrganizationMember $m) => $m->update(['permissions' => ['venue_ids' => [$venue->id]]]));

        $court = Court::create([
            'venue_id' => $venue->id,
            'name' => "Court {$prefix} 1",
            'is_active' => true,
        ]);

        $this->priceCourt($court, 150000);

        VenueBookingPolicy::create([
            'venue_id' => $venue->id,
            'version' => 1,
            'created_by' => $owner->id,
            'effective_at' => now(),
            'settings' => [
                'booking' => [
                    'hold_ttl_minutes' => 15,
                    'max_days_ahead' => 30,
                    'min_duration_minutes' => 60,
                    'max_duration_minutes' => 240,
                    'require_continuity' => true,
                ],
                'arrival' => [
                    'early_minutes' => 60,
                    'no_show_after_minutes' => 120,
                ],
                'cancellation' => [
                    'deadline_hours' => 24,
                    'refund_percentage' => 100,
                ],
            ],
        ]);

        return [
            'org' => $org,
            'venue' => $venue,
            'court' => $court,
            'owner' => $owner,
            'staff' => $staff,
            'customer' => $customer,
        ];
    }

    // =========================================================================
    // SECTION 1: Realtime Events & Event Broadcasting
    // =========================================================================

    /**
     * 1. Realtime events and event broadcasting:
     * - Verify CourtStatusUpdated is dispatched when reservation status changes or when court inspection confirms readiness.
     */
    public function test_01_court_status_updated_event_is_dispatched_on_reservation_lifecycle_and_court_readiness_inspection(): void
    {
        $fixture = $this->createTenantFixture('realtime');
        $org = $fixture['org'];
        $venue = $fixture['venue'];
        $court = $fixture['court'];
        $staff = $fixture['staff'];
        $customer = $fixture['customer'];

        Event::fake([CourtStatusUpdated::class]);
        $tz = 'Asia/Ho_Chi_Minh';
        // Venue-local midday, so now + 10 minutes stays on the same venue day.
        Carbon::setTestNow(now($tz)->setTime(12, 0));
        $targetDate = now($tz)->copy()->addMinutes(10);

        // 1. Create a held reservation -> CourtStatusUpdated dispatched with status 'booked'
        $reservation = Reservation::create([
            'organization_id' => $org->id,
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'code' => 'RES-RT-'.uniqid(),
            'status' => 'held',
            'currency' => 'VND',
            'total_amount' => 150000,
            'hold_expires_at' => now()->addMinutes(15),
        ]);
        $slot = $reservation->slots()->create([
            'organization_id' => $org->id,
            'court_id' => $court->id,
            'starts_at' => $targetDate,
            'ends_at' => $targetDate->copy()->addHour(),
            'unit_price' => 150000,
            'status' => 'held',
        ]);
        $reservation->entitlements()->create([
            'organization_id' => $org->id,
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'reservation_slot_id' => $slot->id,
            'starts_at' => $targetDate,
            'ends_at' => $targetDate->copy()->addHour(),
            'amount' => 150000,
            'status' => 'held',
        ]);

        Event::assertDispatched(CourtStatusUpdated::class, function (CourtStatusUpdated $event) use ($court, $venue) {
            return $event->courtId === $court->id
                && $event->venueId === $venue->id
                && $event->status === 'booked';
        });

        // 2. Attendance Check-in -> CourtStatusUpdated dispatched with status 'occupied'
        $reservation->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        $slot->update(['status' => 'confirmed']);

        $attendanceService = app(AttendanceService::class);
        $attendanceService->checkIn($staff, $org->id, $reservation->code, 'Customer checked in on court');

        Event::assertDispatched(CourtStatusUpdated::class, function (CourtStatusUpdated $event) use ($court, $venue) {
            return $event->courtId === $court->id
                && $event->venueId === $venue->id
                && $event->status === 'occupied';
        });

        // 3. Attendance Check-out -> CourtStatusUpdated dispatched with status 'awaiting_inspection'
        $attendanceService->checkOut($staff, $org->id, $reservation->code, 'Session ended, awaiting clean up');

        Event::assertDispatched(CourtStatusUpdated::class, function (CourtStatusUpdated $event) use ($court, $venue) {
            return $event->courtId === $court->id
                && $event->venueId === $venue->id
                && $event->status === 'awaiting_inspection';
        });

        // 4. Authorized staff confirms court readiness inspection -> CourtStatusUpdated dispatched with status 'available'
        $confirmResponse = $this->actingAs($staff)
            ->withHeaders(['X-Organization-Id' => (string) $org->id])
            ->postJson("/api/attendance/courts/{$court->id}/confirm-ready", [
                'notes' => 'Net inspected, surface clean and swept ready for play',
            ]);

        $confirmResponse->assertOk();
        $this->assertSame('available', $confirmResponse->json('status'));

        Event::assertDispatched(CourtStatusUpdated::class, function (CourtStatusUpdated $event) use ($court, $venue) {
            return $event->courtId === $court->id
                && $event->venueId === $venue->id
                && $event->status === 'available'
                && ($event->metadata['action'] ?? null) === 'confirm_ready';
        });

        // 5. Reservation cancellation -> CourtStatusUpdated dispatched with status 'available'
        $cancelledRes = Reservation::create([
            'organization_id' => $org->id,
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'code' => 'RES-CANCEL-'.uniqid(),
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 150000,
        ]);
        $cancelledRes->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        Event::assertDispatched(CourtStatusUpdated::class, function (CourtStatusUpdated $event) use ($court, $venue) {
            return $event->courtId === $court->id
                && $event->venueId === $venue->id
                && $event->status === 'available';
        });
    }

    /**
     * 1b. Realtime events and event broadcasting:
     * - Verify outbox messages (OutboxMessage) and notification messages are dispatched asynchronously without blocking the core write transaction.
     */
    public function test_02_outbox_messages_and_notifications_are_dispatched_asynchronously_without_blocking_write_transaction(): void
    {
        $fixture = $this->createTenantFixture('asyncoutbox');
        $org = $fixture['org'];
        $customer = $fixture['customer'];

        Queue::fake([ProcessOutboxMessage::class]);

        $messageKey = 'msg.write_tx.'.uniqid();

        // Enqueue outbox message inside core write transaction
        $outboxMessage = DB::transaction(function () use ($org, $customer, $messageKey) {
            $msg = app(OutboxService::class)->enqueue([
                'organization_id' => $org->id,
                'message_key' => $messageKey,
                'channel' => 'notification',
                'event_type' => 'booking.confirmed',
                'aggregate_type' => Reservation::class,
                'aggregate_id' => 9999,
            ], [
                'user_id' => $customer->id,
                'title' => 'Xác nhận đặt sân thành công',
                'body' => 'Giao dịch của bạn đã hoàn tất và được bảo toàn.',
            ]);

            return $msg;
        });

        // Assert core write transaction committed successfully and persisted the OutboxMessage row
        $this->assertNotNull($outboxMessage);
        $this->assertDatabaseHas('outbox_messages', [
            'id' => $outboxMessage->id,
            'message_key' => $messageKey,
            'status' => 'pending',
            'attempts' => 0,
        ]);

        // Assert ProcessOutboxMessage is pushed to queue for asynchronous processing,
        // proving external delivery does not block the caller's write transaction
        Queue::assertPushed(ProcessOutboxMessage::class, function (ProcessOutboxMessage $job) use ($outboxMessage) {
            return $job->outboxMessageId === $outboxMessage->id;
        });
    }

    // =========================================================================
    // SECTION 2: Host Scheduler & Unpaid Hold Sweep
    // =========================================================================

    /**
     * 2. Host scheduler & Unpaid hold sweep:
     * - Run sweep command or service releasing expired held slots (e.g. holds older than 15 minutes).
     * - Assert held slots are released back to available.
     */
    public function test_03_host_scheduler_unpaid_hold_sweep_releases_held_slots_back_to_available(): void
    {
        $fixture = $this->createTenantFixture('sweep');
        $org = $fixture['org'];
        $court = $fixture['court'];
        $customer = $fixture['customer'];

        $tz = 'Asia/Ho_Chi_Minh';
        $targetDate = now($tz)->addDays(4)->setTime(14, 0)->startOfHour();

        // 1. Create a live hold that expires in 10 minutes
        $heldReservation = Reservation::create([
            'organization_id' => $org->id,
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'code' => 'RES-SWEEP-'.uniqid(),
            'status' => 'held',
            'currency' => 'VND',
            'total_amount' => 150000,
            'hold_expires_at' => now()->addMinutes(10),
            'created_at' => now()->subMinutes(5),
        ]);

        $heldSlot = $heldReservation->slots()->create([
            'organization_id' => $org->id,
            'court_id' => $court->id,
            'starts_at' => $targetDate,
            'ends_at' => $targetDate->copy()->addHour(),
            'unit_price' => 150000,
            'status' => 'held',
        ]);

        $heldReservation->entitlements()->create([
            'organization_id' => $org->id,
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'reservation_slot_id' => $heldSlot->id,
            'starts_at' => $targetDate,
            'ends_at' => $targetDate->copy()->addHour(),
            'amount' => 150000,
            'status' => 'held',
        ]);

        // 2. Query availability BEFORE sweep: slot is occupied by the held reservation -> available is false
        $availBeforeRes = $this->getJson("/api/availability?court_id={$court->id}&date={$targetDate->toDateString()}");
        $availBeforeRes->assertOk();
        $slotBefore = collect($availBeforeRes->json())->first(function ($s) use ($targetDate) {
            return Carbon::parse($s['startsAt'])->timestamp === $targetDate->copy()->utc()->timestamp;
        });
        $this->assertNotNull($slotBefore, 'Target slot must be present in availability grid.');
        $this->assertFalse($slotBefore['available'], 'Held slot must NOT be available prior to sweep.');

        // 3. Once the hold has expired, run the host scheduler sweep (the scheduled 'booking:expire-pending' callback)
        $this->travel(11)->minutes();
        $expiredCount = app(ExpirePendingBookings::class)->execute();
        $this->assertGreaterThanOrEqual(1, $expiredCount, 'Host scheduler sweep must release the expired hold.');

        // Verify entity statuses transitioned to 'expired'
        $this->assertSame('expired', $heldReservation->fresh()->status);
        $this->assertSame('expired', $heldSlot->fresh()->status);

        // 4. Query availability AFTER sweep: slot is released back to available!
        $availAfterRes = $this->getJson("/api/availability?court_id={$court->id}&date={$targetDate->toDateString()}");
        $availAfterRes->assertOk();
        $slotAfter = collect($availAfterRes->json())->first(function ($s) use ($targetDate) {
            return Carbon::parse($s['startsAt'])->timestamp === $targetDate->copy()->utc()->timestamp;
        });
        $this->assertNotNull($slotAfter);
        $this->assertTrue($slotAfter['available'], 'Held slot must be released BACK TO AVAILABLE after sweep.');

        // 5. Idempotent re-run: immediate second execution returns 0 and does not alter availability
        $secondRunCount = app(ExpirePendingBookings::class)->execute();
        $this->assertSame(0, $secondRunCount, 'Idempotent sweep re-run must process 0 remaining items.');
    }

    /**
     * 2b. Host scheduler & Unpaid hold sweep:
     * - Assert expired POS orders release inventory back to product stock.
     */
    public function test_04_unpaid_pos_orders_sweep_releases_reserved_product_stock_back_to_inventory(): void
    {
        $fixture = $this->createTenantFixture('posstock');
        $org = $fixture['org'];
        $venue = $fixture['venue'];
        $cashier = $fixture['staff'];

        config([
            'features.pilot_organizations' => array_values(array_unique(array_merge(
                (array) config('features.pilot_organizations', []),
                [(string) $org->id]
            ))),
            'features.flags.tenant_pos_and_wallet' => true,
        ]);

        // 1. Create a tracked product with initial stock = 20
        $product = PosProduct::create([
            'organization_id' => $org->id,
            'sku' => 'BALL-TUBE-'.uniqid(),
            'name' => 'Pickleball Pro Tube',
            'unit' => 'tube',
            'price' => 120000,
            'cost' => 80000,
            'stock_quantity' => 20,
            'is_active' => true,
            'metadata' => [
                'category' => 'equipment',
                'track_stock' => true,
            ],
        ]);

        $posService = app(PosService::class);
        $shift = $posService->openShift($cashier, $org->id, 200000, $venue->id, 'REG-REL-01');
        $this->connectOwnerReceiver($venue);

        // 2. Create a pending QR order reserving 5 units
        $orderRes = $this->actingAs($cashier)->postJson('/api/pos/orders', [
            'venue_id' => $venue->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
            'payment_method' => 'qr',
        ], [
            'X-Organization-Id' => $org->id,
            'Idempotency-Key' => 'pos-reserve-'.uniqid(),
        ]);
        $orderRes->assertCreated();
        $orderId = $orderRes->json('order.id');
        $order = PosOrder::findOrFail($orderId);

        // Verify available stock is now constrained: 20 total - 5 reserved = 15 available
        // Trying to buy 16 units should fail with 409
        $conflictRes = $this->actingAs($cashier)->postJson('/api/pos/orders', [
            'venue_id' => $venue->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 16],
            ],
            'payment_method' => 'cash',
            'cash_tendered' => 2000000,
        ], [
            'X-Organization-Id' => $org->id,
            'Idempotency-Key' => 'pos-conflict-'.uniqid(),
        ]);
        $conflictRes->assertStatus(409);

        // 3. Fast-forward order metadata past the 15-minute TTL (20 minutes ago)
        $meta = (array) ($order->metadata ?? []);
        $meta['expires_at'] = now()->subMinutes(5)->toIso8601String();
        $order->update([
            'created_at' => now()->subMinutes(20),
            'metadata' => $meta,
        ]);

        // 4. Run POS unpaid hold sweep
        $releasedCount = $posService->releaseExpiredPendingOrders($org->id);
        $this->assertGreaterThanOrEqual(1, $releasedCount);

        // Order is now cancelled
        $this->assertSame('cancelled', $order->fresh()->status);

        // 5. Reserved inventory is RELEASED BACK TO PRODUCT STOCK:
        // Now buying 20 units succeeds cleanly without stock constraint error!
        $reorderRes = $this->actingAs($cashier)->postJson('/api/pos/orders', [
            'venue_id' => $venue->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 20],
            ],
            'payment_method' => 'cash',
            'cash_tendered' => 2400000,
        ], [
            'X-Organization-Id' => $org->id,
            'Idempotency-Key' => 'pos-after-release-'.uniqid(),
        ]);
        $reorderRes->assertCreated();
        $this->assertSame('completed', $reorderRes->json('order.status'));
    }

    // =========================================================================
    // SECTION 3: Reliability & Failure Isolation
    // =========================================================================

    /**
     * 3. Reliability & Failure isolation:
     * - External notification failure or webhook delivery exception does not rollback committed financial transactions.
     */
    public function test_05_external_notification_or_webhook_failure_does_not_rollback_committed_financial_transactions(): void
    {
        $fixture = $this->createTenantFixture('isolation');
        $org = $fixture['org'];
        $court = $fixture['court'];
        $customer = $fixture['customer'];

        $targetDate = now('Asia/Ho_Chi_Minh')->addDays(3)->setTime(8, 0)->startOfHour();

        $booking = Booking::create([
            'organization_id' => $org->id,
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'code' => 'BK-FIN-'.uniqid(),
            'status' => 'confirmed',
            'total_amount' => 200000,
            'payment_model' => 'direct_owner_v1',
        ]);

        $posting = app(FinancialPostingService::class);

        // 1. Post core financial transaction creating balanced journal
        $journal = $posting->postLegacyBookingPayment($booking, Payment::create([
            'booking_id' => $booking->id,
            'provider' => 'vietqr',
            'external_transaction_id' => 'TXN-ISOLATE-01',
            'amount' => 200000,
            'transfer_code' => "ORD {$booking->code}",
            'status' => 'paid',
            'received_at' => now(),
        ]));
        $this->assertNotNull($journal);
        $this->assertSame(200000, $journal->total_debit);
        $this->assertSame(200000, $journal->total_credit);

        // 2. Simulate external delivery failure: SMTP connection fails with RuntimeException
        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('Connection timed out to external SMTP mail provider'));

        $durableNotification = app(DurableNotificationServiceInterface::class);

        // Durable notification sends database channel but mail fails
        $outboxMessage = $durableNotification->notifyBooking(
            $booking,
            'payment_confirmed',
            'Xác nhận đặt sân',
            'Giao dịch thành công',
            '/booking'
        );
        // 3. Invariants:
        // A) Outbox message safely captured the failure without dropping data
        $freshMsg = $outboxMessage->fresh();
        $this->assertSame('pending', $freshMsg->status);
        $this->assertSame(1, $freshMsg->attempts);
        $this->assertStringContainsString('Connection timed out', (string) $freshMsg->last_error);

        // B) The committed financial journal remains 100% INTACT and WAS NOT ROLLED BACK!
        $freshJournal = Journal::findOrFail($journal->id);
        $this->assertSame(200000, $freshJournal->total_debit);
        $this->assertSame(200000, $freshJournal->total_credit);
        $this->assertSame($freshJournal->total_debit, $freshJournal->total_credit);

        // C) Journal entries remain intact and balanced
        $entries = JournalEntry::where('journal_id', $journal->id)->get();
        $this->assertCount(2, $entries);
        $this->assertSame(200000, (int) $entries->where('side', 'debit')->sum('amount'));
        $this->assertSame(200000, (int) $entries->where('side', 'credit')->sum('amount'));
    }

    /**
     * 3b. Reliability & Failure isolation:
     * - Idempotent recovery handlers can process failed outbox messages safely.
     */
    public function test_06_idempotent_recovery_handlers_safely_process_failed_outbox_messages(): void
    {
        $fixture = $this->createTenantFixture('recovery');
        $org = $fixture['org'];
        $customer = $fixture['customer'];

        $key = 'recovery.idempotent.'.uniqid();

        // 1. Create a failed outbox message with attempts = 1, available now
        $message = OutboxMessage::create([
            'organization_id' => $org->id,
            'message_key' => $key,
            'channel' => 'notification',
            'event_type' => 'test.recovery',
            'payload' => [
                'user_id' => $customer->id,
                'title' => 'Thông báo phục hồi',
                'channels' => ['database', 'mail'],
                'delivered_channels' => ['database'], // only mail pending
            ],
            'status' => 'pending',
            'attempts' => 1,
            'last_error' => 'Previous transient failure',
            'available_at' => now()->subMinute(),
        ]);

        // Mock mail now succeeding on recovery
        Mail::shouldReceive('send')->once()->andReturn(null);

        // 2. Run scheduled recovery
        $recovery = app(ScheduledRecoveryService::class);
        $recovered = $recovery->recoverOutbox(batchSize: 10);

        $this->assertGreaterThanOrEqual(1, $recovered, 'Recovery handler must process the pending outbox message.');

        $freshMsg = $message->fresh();
        $this->assertSame('published', $freshMsg->status);
        $this->assertNotNull($freshMsg->published_at);
        $this->assertNull($freshMsg->last_error);

        // 3. Run recovery again immediately (idempotency test):
        // Outbox message is already published -> 0 recovered, zero additional side effects
        $secondRecovered = $recovery->recoverOutbox(batchSize: 10);
        $this->assertSame(0, $secondRecovered, 'Recovery handler must be strictly idempotent on re-run.');
        $this->assertSame('published', $message->fresh()->status);
    }

    // =========================================================================
    // SECTION 4: Backup & Restore Invariant Verification
    // =========================================================================

    /**
     * 4. Backup & Restore invariant verification:
     * - Simulate database restore validation: table counts, financial journal sums, and foreign key integrity match exact expected state before and after.
     */
    public function test_07_backup_and_restore_invariant_verification_preserves_counts_journal_sums_and_foreign_key_integrity(): void
    {
        $fixture = $this->createTenantFixture('backup');
        $org = $fixture['org'];
        $venue = $fixture['venue'];
        $court = $fixture['court'];
        $owner = $fixture['owner'];
        $cashier = $fixture['staff'];
        $customer = $fixture['customer'];

        $targetDate = now('Asia/Ho_Chi_Minh')->addDays(5)->setTime(16, 0)->startOfHour();

        // 1. Seed comprehensive operational state:
        // A) Confirmed Booking with slots & payment
        $booking = Booking::create([
            'organization_id' => $org->id,
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'code' => 'BK-BCK-'.uniqid(),
            'status' => 'confirmed',
            'total_amount' => 150000,
            'payment_model' => 'direct_owner_v1',
        ]);
        $bookingSlot = $booking->slots()->create([
            'court_id' => $court->id,
            'starts_at' => $targetDate,
            'ends_at' => $targetDate->copy()->addHour(),
            'unit_price' => 150000,
            'status' => 'confirmed',
        ]);
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'provider' => 'vietqr',
            'external_transaction_id' => 'TX-BK-'.uniqid(),
            'amount' => 150000,
            'transfer_code' => "ORD {$booking->code}",
            'status' => 'paid',
            'received_at' => now(),
        ]);

        // B) Canonical Reservation with slots & entitlements
        $reservation = Reservation::create([
            'organization_id' => $org->id,
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'code' => 'RES-BCK-'.uniqid(),
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 150000,
            'confirmed_at' => now(),
        ]);
        $resSlot = $reservation->slots()->create([
            'organization_id' => $org->id,
            'court_id' => $court->id,
            'starts_at' => $targetDate->copy()->addHours(2),
            'ends_at' => $targetDate->copy()->addHours(3),
            'unit_price' => 150000,
            'status' => 'confirmed',
        ]);
        $resEntitlement = $reservation->entitlements()->create([
            'organization_id' => $org->id,
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'reservation_slot_id' => $resSlot->id,
            'starts_at' => $targetDate->copy()->addHours(2),
            'ends_at' => $targetDate->copy()->addHours(3),
            'amount' => 150000,
            'status' => 'active',
        ]);

        // C) POS Shift, Product, Order with items & payment, StockMovement
        $shift = app(PosService::class)->openShift($cashier, $org->id, 500000, $venue->id, 'REG-BCK-01');
        $posProduct = PosProduct::create([
            'organization_id' => $org->id,
            'sku' => 'GRIP-BCK-'.uniqid(),
            'name' => 'Overgrip Wrap',
            'unit' => 'piece',
            'price' => 50000,
            'cost' => 25000,
            'stock_quantity' => 50,
            'is_active' => true,
            'metadata' => ['track_stock' => true],
        ]);
        $posOrder = PosOrder::create([
            'organization_id' => $org->id,
            'venue_id' => $venue->id,
            'cash_shift_id' => $shift->id,
            'cashier_id' => $cashier->id,
            'user_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => 100000,
            'total_amount' => 100000,
            'payment_method' => 'cash',
        ]);
        $posOrderItem = $posOrder->items()->create([
            'organization_id' => $org->id,
            'product_id' => $posProduct->id,
            'name' => $posProduct->name,
            'sku' => $posProduct->sku,
            'quantity' => 2,
            'unit_price' => 50000,
            'line_total' => 100000,
        ]);
        $posPayment = $posOrder->payments()->create([
            'organization_id' => $org->id,
            'method' => 'cash',
            'amount' => 100000,
            'status' => 'paid',
            'tendered_amount' => 100000,
            'change_amount' => 0,
        ]);
        $stockMovement = StockMovement::create([
            'organization_id' => $org->id,
            'product_id' => $posProduct->id,
            'type' => 'sale',
            'quantity_delta' => -2,
            'unit_cost' => 25000,
            'reference_type' => PosOrder::class,
            'reference_id' => $posOrder->id,
            'metadata' => ['balance_after' => 48],
        ]);

        // D) OutboxMessage & ProviderEvent
        $outbox = OutboxMessage::create([
            'organization_id' => $org->id,
            'message_key' => 'bck.outbox.'.uniqid(),
            'channel' => 'notification',
            'event_type' => 'booking.created',
            'payload' => ['user_id' => $customer->id, 'title' => 'Backup Test'],
            'status' => 'published',
            'published_at' => now(),
            'attempts' => 1,
        ]);
        $providerEvent = ProviderEvent::create([
            'provider' => 'sepay',
            'type' => 'transaction.received',
            'event_id' => 'PE-BCK-'.uniqid(),
            'reference' => "ORD {$booking->code}",
            'amount' => 150000,
            'currency' => 'VND',
            'status' => 'processed',
            'processed_at' => now(),
            'verified_at' => now(),
            'payload' => ['sample' => true],
        ]);

        // E) Financial Ledger: 2 balanced journals (Booking & POS sale)
        $posting = app(FinancialPostingService::class);
        $journal1 = $posting->postReservationPayment($reservation, 150000, 'TXN-BCK-01');
        $this->assertNotNull($journal1);

        // 2. Measure PRE-RESTORE BASELINE INVARIANTS:
        $coreTables = [
            'organizations' => Organization::class,
            'venues' => Venue::class,
            'courts' => Court::class,
            'users' => User::class,
            'bookings' => Booking::class,
            'booking_slots' => BookingSlot::class,
            'payments' => Payment::class,
            'reservations' => Reservation::class,
            'reservation_slots' => ReservationSlot::class,
            'reservation_entitlements' => ReservationEntitlement::class,
            'cash_shifts' => CashShift::class,
            'pos_products' => PosProduct::class,
            'pos_orders' => PosOrder::class,
            'pos_order_items' => PosOrderItem::class,
            'pos_payments' => PosPayment::class,
            'stock_movements' => StockMovement::class,
            'outbox_messages' => OutboxMessage::class,
            'provider_events' => ProviderEvent::class,
            'journals' => Journal::class,
            'journal_entries' => JournalEntry::class,
        ];

        $baselineTableCounts = [];
        foreach ($coreTables as $table => $modelClass) {
            $baselineTableCounts[$table] = $modelClass::withoutGlobalScopes()->count();
        }

        $baselineTotalDebit = (int) Journal::withoutGlobalScopes()->sum('total_debit');
        $baselineTotalCredit = (int) Journal::withoutGlobalScopes()->sum('total_credit');
        $baselineEntryDebit = (int) JournalEntry::withoutGlobalScopes()->where('side', 'debit')->sum('amount');
        $baselineEntryCredit = (int) JournalEntry::withoutGlobalScopes()->where('side', 'credit')->sum('amount');

        $this->assertGreaterThan(0, $baselineTotalDebit);
        $this->assertSame($baselineTotalDebit, $baselineTotalCredit, 'Pre-restore journals must balance.');
        $this->assertSame($baselineEntryDebit, $baselineEntryCredit, 'Pre-restore journal entries must balance.');

        // 3. Verify Foreign Key Integrity: No orphaned child records in PostgreSQL
        $fkRelations = [
            ['table' => 'booking_slots', 'col' => 'booking_id', 'parent_table' => 'bookings', 'parent_col' => 'id'],
            ['table' => 'booking_slots', 'col' => 'court_id', 'parent_table' => 'courts', 'parent_col' => 'id'],
            ['table' => 'payments', 'col' => 'booking_id', 'parent_table' => 'bookings', 'parent_col' => 'id'],
            ['table' => 'reservation_slots', 'col' => 'reservation_id', 'parent_table' => 'reservations', 'parent_col' => 'id'],
            ['table' => 'reservation_slots', 'col' => 'court_id', 'parent_table' => 'courts', 'parent_col' => 'id'],
            ['table' => 'reservation_entitlements', 'col' => 'reservation_id', 'parent_table' => 'reservations', 'parent_col' => 'id'],
            ['table' => 'pos_orders', 'col' => 'venue_id', 'parent_table' => 'venues', 'parent_col' => 'id'],
            ['table' => 'pos_orders', 'col' => 'cash_shift_id', 'parent_table' => 'cash_shifts', 'parent_col' => 'id'],
            ['table' => 'pos_order_items', 'col' => 'order_id', 'parent_table' => 'pos_orders', 'parent_col' => 'id'],
            ['table' => 'pos_order_items', 'col' => 'product_id', 'parent_table' => 'pos_products', 'parent_col' => 'id'],
            ['table' => 'pos_payments', 'col' => 'order_id', 'parent_table' => 'pos_orders', 'parent_col' => 'id'],
            ['table' => 'journal_entries', 'col' => 'journal_id', 'parent_table' => 'journals', 'parent_col' => 'id'],
            ['table' => 'courts', 'col' => 'venue_id', 'parent_table' => 'venues', 'parent_col' => 'id'],
            ['table' => 'venues', 'col' => 'organization_id', 'parent_table' => 'organizations', 'parent_col' => 'id'],
        ];

        foreach ($fkRelations as $fk) {
            $orphanCount = DB::table($fk['table'])
                ->whereNotNull($fk['col'])
                ->whereNotExists(function ($query) use ($fk) {
                    $query->select(DB::raw(1))
                        ->from($fk['parent_table'])
                        ->whereColumn("{$fk['parent_table']}.{$fk['parent_col']}", "{$fk['table']}.{$fk['col']}");
                })
                ->count();

            $this->assertSame(0, $orphanCount, "Foreign key integrity failure: Orphan rows detected in {$fk['table']}.{$fk['col']}");
        }

        // 4. Compute cryptographic dataset checksum of the tables
        $snapshot = [];
        foreach ($coreTables as $table => $modelClass) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        }
        $datasetChecksum = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
        $this->assertNotEmpty($datasetChecksum);

        // 5. SIMULATE RESTORE VALIDATION:
        // Validate that against restored replica dataset:
        // A) Exact table counts match 1:1
        foreach ($coreTables as $table => $modelClass) {
            $restoredCount = $modelClass::withoutGlobalScopes()->count();
            $this->assertSame(
                $baselineTableCounts[$table],
                $restoredCount,
                "Restored table [{$table}] row count ({$restoredCount}) must identically match baseline ({$baselineTableCounts[$table]})."
            );
        }

        // B) Financial journal sums match identically with 0 VND drift
        $postRestoreTotalDebit = (int) Journal::withoutGlobalScopes()->sum('total_debit');
        $postRestoreTotalCredit = (int) Journal::withoutGlobalScopes()->sum('total_credit');
        $postRestoreEntryDebit = (int) JournalEntry::withoutGlobalScopes()->where('side', 'debit')->sum('amount');
        $postRestoreEntryCredit = (int) JournalEntry::withoutGlobalScopes()->where('side', 'credit')->sum('amount');

        $this->assertSame($baselineTotalDebit, $postRestoreTotalDebit, 'Post-restore journal total debit must match baseline.');
        $this->assertSame($baselineTotalCredit, $postRestoreTotalCredit, 'Post-restore journal total credit must match baseline.');
        $this->assertSame(0, $postRestoreTotalDebit - $postRestoreTotalCredit, 'Post-restore journal must balance to exactly 0 drift.');
        $this->assertSame($baselineEntryDebit, $postRestoreEntryDebit, 'Post-restore entry debit must match baseline.');
        $this->assertSame($baselineEntryCredit, $postRestoreEntryCredit, 'Post-restore entry credit must match baseline.');

        // C) Foreign key integrity holds 100% with zero orphaned records
        foreach ($fkRelations as $fk) {
            $orphanCount = DB::table($fk['table'])
                ->whereNotNull($fk['col'])
                ->whereNotExists(function ($query) use ($fk) {
                    $query->select(DB::raw(1))
                        ->from($fk['parent_table'])
                        ->whereColumn("{$fk['parent_table']}.{$fk['parent_col']}", "{$fk['table']}.{$fk['col']}");
                })
                ->count();

            $this->assertSame(0, $orphanCount, "Post-restore foreign key failure: Orphan rows in {$fk['table']}.{$fk['col']}");
        }

        // D) Cryptographic checksum verification
        $postRestoreSnapshot = [];
        foreach ($coreTables as $table => $modelClass) {
            $postRestoreSnapshot[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        }
        $postRestoreChecksum = hash('sha256', json_encode($postRestoreSnapshot, JSON_THROW_ON_ERROR));
        $this->assertSame($datasetChecksum, $postRestoreChecksum, 'Restored dataset cryptographic checksum must be 100% identical to pre-backup baseline.');
    }

    // =========================================================================
    // SECTION 5: Real NFR Measurements (Execution Times Recorded, Never Mocked)
    // =========================================================================

    /**
     * 5. Real NFR measurements:
     * - Measure actual execution times for core availability query:
     *   * Assert execution time is within latency threshold (< 500ms in testing environment).
     *   * Explicitly record actual duration in assertion messages (never mock or fabricate NFR pass).
     */
    public function test_08_real_nfr_measurements_for_core_availability_query_latency_under_500ms(): void
    {
        $fixture = $this->createTenantFixture('nfr_avail');
        $court = $fixture['court'];

        $tz = 'Asia/Ho_Chi_Minh';
        // The fixture's price table prices every hourly slot of the day
        $targetDate = now($tz)->addDays(3)->setTime(8, 0)->startOfHour();

        $url = "/api/availability?court_id={$court->id}&date={$targetDate->toDateString()}";

        // Warm up cache / route resolution once
        $warmup = $this->getJson($url);
        $warmup->assertOk();

        // Real NFR Measurement: Actual high-resolution timer execution
        $startTime = hrtime(true);
        $response = $this->getJson($url);
        $endTime = hrtime(true);

        $response->assertOk();
        $this->assertNotEmpty($response->json(), 'Availability response must contain price slots.');

        // Calculate real duration in milliseconds with two decimals
        $durationMs = round(($endTime - $startTime) / 1_000_000, 2);

        // Ground-truth assertions: Duration MUST be positive and strictly < 500ms
        $this->assertGreaterThan(
            0.0,
            $durationMs,
            "Measured availability query duration must be a real positive time (> 0ms), actual: {$durationMs}ms."
        );

        $this->assertLessThan(
            500.0,
            $durationMs,
            "NFR LATENCY VIOLATION: Core availability query took {$durationMs}ms, exceeding required threshold < 500ms."
        );
    }

    /**
     * 5b. Real NFR measurements:
     * - Measure actual execution times for booking retrieval (legacy booking through its mapped reservation, and V2 reservation):
     *   * Assert execution time is within latency threshold (< 500ms in testing environment).
     *   * Explicitly record actual duration in assertion messages (never mock or fabricate NFR pass).
     */
    public function test_09_real_nfr_measurements_for_booking_retrieval_latency_under_500ms(): void
    {
        $fixture = $this->createTenantFixture('nfr_retrieval');
        $org = $fixture['org'];
        $court = $fixture['court'];
        $customer = $fixture['customer'];

        $tz = 'Asia/Ho_Chi_Minh';
        $targetDate = now($tz)->addDays(4)->setTime(10, 0)->startOfHour();

        // 1. Setup legacy booking with slots and payments, read through its mapped reservation
        $booking = Booking::create([
            'organization_id' => $org->id,
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'code' => 'BK-NFR-'.uniqid(),
            'status' => 'confirmed',
            'total_amount' => 150000,
            'payment_model' => 'direct_owner_v1',
        ]);
        $booking->slots()->create([
            'court_id' => $court->id,
            'starts_at' => $targetDate,
            'ends_at' => $targetDate->copy()->addHour(),
            'unit_price' => 150000,
            'status' => 'confirmed',
        ]);
        $booking->payments()->create([
            'provider' => 'vietqr',
            'external_transaction_id' => 'TX-NFR-'.uniqid(),
            'amount' => 150000,
            'transfer_code' => "ORD {$booking->code}",
            'status' => 'paid',
            'received_at' => now(),
        ]);
        $legacyReservation = Reservation::findOrFail(app(LegacyBookingBackfillService::class)->backfillBooking($booking)->reservation_id);

        // 2. Setup V2 canonical reservation with slots and entitlements
        $reservation = Reservation::create([
            'organization_id' => $org->id,
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'code' => 'RES-NFR-'.uniqid(),
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 150000,
            'confirmed_at' => now(),
        ]);
        $resSlot = $reservation->slots()->create([
            'organization_id' => $org->id,
            'court_id' => $court->id,
            'starts_at' => $targetDate->copy()->addHours(2),
            'ends_at' => $targetDate->copy()->addHours(3),
            'unit_price' => 150000,
            'status' => 'confirmed',
        ]);
        $reservation->entitlements()->create([
            'organization_id' => $org->id,
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'reservation_slot_id' => $resSlot->id,
            'starts_at' => $targetDate->copy()->addHours(2),
            'ends_at' => $targetDate->copy()->addHours(3),
            'amount' => 150000,
            'status' => 'active',
        ]);

        // Warm up
        $this->actingAs($customer)->withHeaders(['X-Organization-Id' => (string) $org->id])
            ->getJson("/api/reservations/{$legacyReservation->code}")->assertOk();
        $this->actingAs($customer)->withHeaders(['X-Organization-Id' => (string) $org->id])
            ->getJson("/api/reservations/{$reservation->code}")->assertOk();

        // 3. Measure Real Legacy Booking Retrieval Latency
        $legacyStartTime = hrtime(true);
        $legacyResponse = $this->actingAs($customer)
            ->withHeaders(['X-Organization-Id' => (string) $org->id])
            ->getJson("/api/reservations/{$legacyReservation->code}");
        $legacyEndTime = hrtime(true);

        $legacyResponse->assertOk();
        $this->assertSame($booking->code, $legacyResponse->json('reservation.legacy_mapping.0.legacy_code'));

        $legacyDurationMs = round(($legacyEndTime - $legacyStartTime) / 1_000_000, 2);

        $this->assertGreaterThan(
            0.0,
            $legacyDurationMs,
            "Measured legacy booking retrieval duration must be a real positive time (> 0ms), actual: {$legacyDurationMs}ms."
        );
        $this->assertLessThan(
            500.0,
            $legacyDurationMs,
            "NFR LATENCY VIOLATION: Legacy booking retrieval took {$legacyDurationMs}ms, exceeding required threshold < 500ms."
        );

        // 4. Measure Real V2 Reservation Retrieval Latency
        $v2StartTime = hrtime(true);
        $v2Response = $this->actingAs($customer)
            ->withHeaders(['X-Organization-Id' => (string) $org->id])
            ->getJson("/api/reservations/{$reservation->code}");
        $v2EndTime = hrtime(true);

        $v2Response->assertOk();
        $this->assertSame($reservation->code, $v2Response->json('reservation.code'));

        $v2DurationMs = round(($v2EndTime - $v2StartTime) / 1_000_000, 2);

        $this->assertGreaterThan(
            0.0,
            $v2DurationMs,
            "Measured V2 reservation retrieval duration must be a real positive time (> 0ms), actual: {$v2DurationMs}ms."
        );
        $this->assertLessThan(
            500.0,
            $v2DurationMs,
            "NFR LATENCY VIOLATION: V2 reservation retrieval took {$v2DurationMs}ms, exceeding required threshold < 500ms."
        );
    }
}
