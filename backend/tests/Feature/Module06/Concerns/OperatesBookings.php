<?php

namespace Tests\Feature\Module06\Concerns;

use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsVenues;
use Tests\Feature\Module02\Concerns\SeedsBookings;

/**
 * Module 06 on-site commands as the staff app sends them, on 2026-11-03 venue time.
 *
 *     $booking = $this->booking($court, $this->at('10:00'));
 *     $this->travelTo($this->at('09:50'));
 *     $this->attend($staff, 'check-in', $booking)->assertCreated();
 */
trait OperatesBookings
{
    use BuildsVenues;
    use SeedsBookings;

    protected function attend(User $user, string $command, Reservation $booking, ?string $code = null): TestResponse
    {
        return $this->actingAs($user)->postJson("/api/attendance/{$command}", ['code' => $code ?? $booking->code], [
            'X-Organization-ID' => (string) $booking->organization_id,
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
    }

    protected function at(string $time, string $day = '2026-11-03'): CarbonInterface
    {
        return Carbon::parse("{$day} {$time}", 'Asia/Ho_Chi_Minh');
    }
}
