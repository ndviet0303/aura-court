<?php

use App\Services\BookingService;
use App\Services\RecurringSeriesService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\RunDailyReconciliation;
use App\Models\Organization;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(BookingService::class)->expirePending())->everyMinute();
Schedule::call(fn () => app(BookingService::class)->notifyExpiring())->everyMinute();
Schedule::call(fn () => Organization::query()->where('status', 'active')->pluck('id')->each(fn (int $id) => RunDailyReconciliation::dispatch($id)))->dailyAt('01:00');
Schedule::command('monitor:queue-lag')->everyMinute();
Schedule::call(fn () => app(RecurringSeriesService::class)->notifyUpcomingInvoices())->daily();
Schedule::call(fn () => app(RecurringSeriesService::class)->markOverdueAndReleaseFuture())->hourly();
