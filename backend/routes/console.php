<?php

use App\Jobs\RunDailyReconciliation;
use App\Models\Organization;
use App\Services\BookingService;
use App\Services\Infrastructure\ScheduledRecoveryService;
use App\Services\RecurringSeriesService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 1. Booking lifecycle tasks with named task-level overlap protection
Schedule::call(fn () => app(BookingService::class)->expirePending())
    ->name('booking:expire-pending')
    ->withoutOverlapping(10)
    ->everyMinute();

Schedule::call(fn () => app(BookingService::class)->notifyExpiring())
    ->name('booking:notify-expiring')
    ->withoutOverlapping(10)
    ->everyMinute();

// 2. Worker-free failure recovery for outbox and provider events
Schedule::call(fn () => app(ScheduledRecoveryService::class)->recoverOutbox())
    ->name('recovery:outbox')
    ->withoutOverlapping(10)
    ->everyMinute();

Schedule::call(fn () => app(ScheduledRecoveryService::class)->recoverProviderEvents())
    ->name('recovery:provider-events')
    ->withoutOverlapping(10)
    ->everyMinute();

// 3. Daily reconciliation with per-organization failure isolation and overlap protection
Schedule::call(function () {
    Organization::query()->where('status', 'active')->pluck('id')->each(function (int $id) {
        try {
            RunDailyReconciliation::dispatch($id);
        } catch (\Throwable $exception) {
            Log::error("Daily reconciliation failed for organization {$id}: {$exception->getMessage()}", [
                'organization_id' => $id,
                'exception' => $exception,
            ]);
        }
    });
})->name('finance:daily-reconciliation')
    ->withoutOverlapping(60)
    ->dailyAt('01:00');

// 4. Invoicing schedules with overlap protection
Schedule::call(fn () => app(RecurringSeriesService::class)->notifyUpcomingInvoices())
    ->name('invoices:notify-upcoming')
    ->withoutOverlapping(30)
    ->daily();

Schedule::call(fn () => app(RecurringSeriesService::class)->markOverdueAndReleaseFuture())
    ->name('invoices:mark-overdue')
    ->withoutOverlapping(30)
    ->hourly();

// 5. Queue depth monitoring only when running asynchronous queue drivers (omitted in sync mode)
if (config('queue.default') !== 'sync') {
    Schedule::command('monitor:queue-lag')->name('monitor:queue-lag')->everyMinute();
}
