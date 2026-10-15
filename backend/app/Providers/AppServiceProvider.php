<?php

namespace App\Providers;

use App\Contracts\Infrastructure\SubscriptionPaymentAdapterInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Infrastructure\Payment\ProviderNeutralSubscriptionPaymentAdapter;
use App\Services\Auth\EmailLinkThrottle;
use App\Services\Infrastructure\ObservabilityService;
use App\Services\Infrastructure\TenantContext;
use App\Services\Payment\PaymentProviderManager;
use App\Services\Payment\PayOsPaymentProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        $this->configureAuthRateLimits();
        // BR-23: remember when each session signed in, so a later account lock can end it.
        Event::listen(Login::class, function (): void {
            if (app()->bound('session.store') && request()->hasSession()) {
                request()->session()->put('auth_at', now()->getTimestamp());
            }
        });
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

    private function configureAuthRateLimits(): void
    {
        $tooMany = fn (Request $request, array $headers) => response()->json(
            ['message' => 'Bạn thao tác quá nhiều lần. Vui lòng thử lại sau ít phút.'],
            429,
            $headers,
        );

        RateLimiter::for('auth-login', fn (Request $request) => [
            Limit::perMinute(5)->by('account:'.Str::lower(trim((string) $request->input('email'))).'|'.$request->ip())->response($tooMany),
            Limit::perMinute(20)->by('ip:'.$request->ip())->response($tooMany),
        ]);
        RateLimiter::for('auth-register', fn (Request $request) => Limit::perMinute(5)->by($request->ip())->response($tooMany));
        RateLimiter::for('auth-reset', fn (Request $request) => Limit::perMinute(5)->by($request->ip())->response($tooMany));
        // BR-05/BR-10: verification and reset emails at least 60 s apart and at most 5 an hour per account.
        RateLimiter::for('auth-email-link', function (Request $request) use ($tooMany) {
            $account = EmailLinkThrottle::account($request->user()->email ?? (string) $request->input('email'));

            return [
                Limit::perMinute(1)->by('minute:'.$account)->response($tooMany),
                Limit::perHour(5)->by('hour:'.$account)->response($tooMany),
                Limit::perMinute(10)->by('ip:'.$request->ip())->response($tooMany),
            ];
        });
    }
}
