<?php

namespace App\Providers;

use App\Contracts\Infrastructure\SubscriptionPaymentAdapterInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Infrastructure\Payment\ProviderNeutralSubscriptionPaymentAdapter;
use App\Services\Infrastructure\ObservabilityService;
use App\Services\Infrastructure\TenantContext;
use App\Services\Payment\PaymentProviderManager;
use App\Services\Payment\PayOsPaymentProvider;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, fn () => new TenantContext);
        $this->app->singleton(PaymentProviderInterface::class, PayOsPaymentProvider::class);
        $this->app->singleton(PaymentProviderManager::class, fn ($app) => new PaymentProviderManager(
            $app->make(PayOsPaymentProvider::class),
        ));
        $this->app->singleton(
            SubscriptionPaymentAdapterInterface::class,
            ProviderNeutralSubscriptionPaymentAdapter::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Queue::before(function (JobProcessing $event): void {
            app(ObservabilityService::class)->record('queue.job_started', ['job' => $event->job->resolveName()]);
        });
        Queue::after(function (JobProcessed $event): void {
            app(ObservabilityService::class)->record('queue.job_completed', ['job' => $event->job->resolveName(), 'duration_ms' => $event->job->payload()['displayName'] ?? null]);
        });
        Queue::failing(function (JobFailed $event): void {
            app(ObservabilityService::class)->record('queue.job_failed', ['job' => $event->job->resolveName(), 'error' => $event->exception->getMessage()], 'error');
        });
        Queue::exceptionOccurred(function (JobExceptionOccurred $event): void {
            app(ObservabilityService::class)->metric('queue.job_exception');
        });
        DB::listen(function (QueryExecuted $query): void {
            if (str_contains(strtolower($query->sql), 'for update') && $query->time >= (int) config('observability.lock_contention_alert_ms', 500)) {
                app(ObservabilityService::class)->alert('lock_contention', ['duration_ms' => $query->time, 'query' => $query->sql]);
            }
        });
    }
}
