<?php

namespace Tests\Feature\RuntimeExecution;

use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Services\Infrastructure\ObservabilityService;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledCallbackResolutionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression for D32: a scheduled closure that referenced a mis-imported service resolved to a
     * non-existent class and threw a BindingResolutionException at tick time, silently no-opping the
     * sweep. This fails if any registered closure cannot resolve its target from the container.
     */
    public function test_every_scheduled_closure_resolves_its_target_service(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => $event instanceof CallbackEvent);

        $this->assertNotEmpty($events, 'The scheduler must register closure-based tasks.');

        foreach ($events as $event) {
            $name = $event->description ?? '(unnamed)';

            try {
                $event->run($this->app);
            } catch (BindingResolutionException $e) {
                $this->fail("Scheduled closure [{$name}] references an unresolvable service: {$e->getMessage()}");
            } catch (\Throwable $e) {
                // Non-binding runtime errors are outside the scope of this container-resolution guard.
            }
        }

        // No closure raised a container resolution failure.
        $this->assertTrue(true);
    }

    public function test_v1_adoption_snapshot_is_scheduled_daily_over_thirty_days(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => ($e->description ?? null) === 'v1:adoption-snapshot');

        $this->assertNotNull($event, 'v1:adoption-snapshot must be registered.');
        $this->assertStringContainsString('v1:adoption-snapshot --days=30', $event->command);
        $this->assertSame('0 1 * * *', $event->expression);
    }

    public function test_failing_scheduled_invocation_emits_observable_alert(): void
    {
        $spy = new class extends ObservabilityService
        {
            /** @var array<int, array{name: string, context: array}> */
            public array $captured = [];

            public function alert(string $name, array $context = []): void
            {
                $this->captured[] = ['name' => $name, 'context' => $context];
            }
        };
        $this->app->instance(ObservabilityService::class, $spy);

        // Force the booking-expiry closure to fail at resolution time, mirroring the D32 failure mode.
        $this->app->bind(ExpirePendingBookings::class, fn () => throw new \RuntimeException('injected resolution failure'));

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => ($e->description ?? null) === 'booking:expire-pending');
        $this->assertNotNull($event, 'booking:expire-pending must be registered.');

        try {
            $event->run($this->app);
        } catch (\Throwable $e) {
            // The scheduler rethrows after firing onFailure; the caught throw is expected.
        }

        $this->assertNotEmpty($spy->captured, 'A failing scheduled invocation must emit an observable alert.');
        $this->assertSame('scheduled_task_failed', $spy->captured[0]['name']);
        $this->assertSame('booking:expire-pending', $spy->captured[0]['context']['task']);
    }
}
