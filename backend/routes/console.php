<?php

use App\Jobs\RunDailyReconciliation;
use App\Models\Organization;
use App\Services\Booking\BookingService;
use App\Services\Booking\RecurringSeriesService;
use App\Services\Infrastructure\ObservabilityService;
use App\Services\Infrastructure\ScheduledRecoveryService;
use App\Services\Payment\SplitPaymentService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Observable failure signal for every worker-free host tick: if a scheduled callback fails to
// resolve (e.g. a mis-imported service) or throws, surface it as an alert instead of silently
// no-opping. Returns a per-task onFailure handler.
$reportScheduleFailure = static fn (string $task): Closure => static function () use ($task): void {
    app(ObservabilityService::class)->alert('scheduled_task_failed', ['task' => $task]);
};

// 1. Booking lifecycle tasks with named task-level overlap protection
Schedule::call(fn () => app(BookingService::class)->expirePending())
    ->name('booking:expire-pending')
    ->withoutOverlapping(10)
    ->everyMinute()
    ->onFailure($reportScheduleFailure('booking:expire-pending'));

Schedule::call(fn () => app(BookingService::class)->notifyExpiring())
    ->name('booking:notify-expiring')
    ->withoutOverlapping(10)
    ->everyMinute()
    ->onFailure($reportScheduleFailure('booking:notify-expiring'));

// 1b. Split-payment group expiry: release the held reservation and mark unpaid shares expired
Schedule::call(fn () => app(SplitPaymentService::class)->expireDue())
    ->name('split:expire-due')
    ->withoutOverlapping(10)
    ->everyMinute()
    ->onFailure($reportScheduleFailure('split:expire-due'));

// 2. Worker-free failure recovery for outbox and provider events
Schedule::call(fn () => app(ScheduledRecoveryService::class)->recoverOutbox())
    ->name('recovery:outbox')
    ->withoutOverlapping(10)
    ->everyMinute()
    ->onFailure($reportScheduleFailure('recovery:outbox'));

Schedule::call(fn () => app(ScheduledRecoveryService::class)->recoverProviderEvents())
    ->name('recovery:provider-events')
    ->withoutOverlapping(10)
    ->everyMinute()
    ->onFailure($reportScheduleFailure('recovery:provider-events'));

Schedule::call(fn () => app(ScheduledRecoveryService::class)->recoverOwnerPaymentReceipts())
    ->name('recovery:owner-payment-receipts')
    ->withoutOverlapping(10)
    ->everyMinute()
    ->onFailure($reportScheduleFailure('recovery:owner-payment-receipts'));

// 3. Daily reconciliation with per-organization failure isolation and overlap protection
Schedule::call(function () {
    Organization::query()->where('status', 'active')->pluck('id')->each(function (int $id) {
        try {
            RunDailyReconciliation::dispatch($id);
        } catch (Throwable $exception) {
            Log::error("Daily reconciliation failed for organization {$id}: {$exception->getMessage()}", [
                'organization_id' => $id,
                'exception' => $exception,
            ]);
        }
    });
})->name('finance:daily-reconciliation')
    ->withoutOverlapping(60)
    ->dailyAt('01:00')
    ->onFailure($reportScheduleFailure('finance:daily-reconciliation'));

// 4. Invoicing schedules with overlap protection
Schedule::call(fn () => app(RecurringSeriesService::class)->notifyUpcomingInvoices())
    ->name('invoices:notify-upcoming')
    ->withoutOverlapping(30)
    ->daily()
    ->onFailure($reportScheduleFailure('invoices:notify-upcoming'));

Schedule::call(fn () => app(RecurringSeriesService::class)->markOverdueAndReleaseFuture())
    ->name('invoices:mark-overdue')
    ->withoutOverlapping(30)
    ->hourly()
    ->onFailure($reportScheduleFailure('invoices:mark-overdue'));

// 4b. Loyalty point expiry: append-only reversals for lots past their book-ahead expiry
Schedule::command('loyalty:expire-points')
    ->name('loyalty:expire-points')
    ->withoutOverlapping(30)
    ->dailyAt('00:30')
    ->onFailure($reportScheduleFailure('loyalty:expire-points'));

// 4c. Platform subscription lifecycle: expire active/trial subscriptions past their end date
Schedule::command('platform:process-expired-subscriptions')
    ->name('subscriptions:process-expired')
    ->withoutOverlapping(30)
    ->hourly()
    ->onFailure($reportScheduleFailure('subscriptions:process-expired'));

// 5. Queue depth monitoring only when running asynchronous queue drivers (omitted in sync mode)
if (config('queue.default') !== 'sync') {
    Schedule::command('monitor:queue-lag')
        ->name('monitor:queue-lag')
        ->everyMinute()
        ->onFailure($reportScheduleFailure('monitor:queue-lag'));
}
