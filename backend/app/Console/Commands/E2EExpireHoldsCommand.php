<?php

namespace App\Console\Commands;

use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Models\Booking;
use App\Models\LegacyBookingMapping;
use App\Models\Organization;
use App\Models\OwnerPaymentAttempt;
use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class E2EExpireHoldsCommand extends Command
{
    protected $signature = 'e2e:expire-holds
        {--run-id= : Unique run ID}
        {--booking-code= : Optional specific booking code to backdate}
        {--reservation-code= : Optional specific reservation code to backdate}
        {--minutes=30 : Number of minutes to backdate timestamps}';

    protected $description = 'Test-only CLI command to backdate hold expiration timestamps and run real expiry sweep';

    public function handle(ExpirePendingBookings $sweep): int
    {
        $runId = trim((string) $this->option('run-id'));
        if ($runId === '' || ! preg_match('/^[a-zA-Z0-9_-]+$/', $runId)) {
            $this->error('CRITICAL: Missing or invalid --run-id');

            return self::FAILURE;
        }

        // Strict target safety check before any mutation
        $env = app()->environment();
        if ($env !== 'testing' && $env !== 'local') {
            $this->error("CRITICAL: Refusing to mutate target in environment '{$env}'. Only 'testing' or 'local' allowed.");

            return self::FAILURE;
        }

        $dbConfig = DB::connection()->getConfig();
        $dbHost = (string) ($dbConfig['host'] ?? '');
        $dbName = (string) ($dbConfig['database'] ?? '');

        $allowedHosts = ['127.0.0.1', 'localhost', '::1', 'bt-server', '100.64.0.15', (string) config('database.connections.'.config('database.default').'.host')];
        if (! in_array($dbHost, $allowedHosts, true)) {
            $this->error("CRITICAL: Refusing remote target '{$dbHost}'.");

            return self::FAILURE;
        }

        $isProdRun = preg_match('/^prod_e2e_[0-9]+_[a-f0-9]{8}$/', $runId) === 1;
        if (! $isProdRun) {
            $expectedDb = "booking_e2e_{$runId}_test";
            if ($dbName === 'booking' || $dbName === 'booking_test') {
                $this->error("CRITICAL: Refusing to expire holds in protected database '{$dbName}'.");

                return self::FAILURE;
            }

            if ($dbName !== $expectedDb) {
                $this->error("CRITICAL: Active database '{$dbName}' does not match expected '{$expectedDb}'.");

                return self::FAILURE;
            }
        }

        $organizationIds = $isProdRun
            ? Organization::where('settings->e2e_run_id', $runId)->pluck('id')->all()
            : null;
        if ($isProdRun && empty($organizationIds)) {
            $this->error('Production hold expiry requires a run-owned test organization.');

            return self::FAILURE;
        }

        $minutes = max(1, (int) $this->option('minutes'));
        $targetTime = now()->subMinutes($minutes);
        $bookingCode = trim((string) $this->option('booking-code'));
        $reservationCode = trim((string) $this->option('reservation-code'));

        $backdatedBookings = [];
        $backdatedReservations = [];

        DB::transaction(function () use ($bookingCode, $reservationCode, $targetTime, $organizationIds, &$backdatedBookings, &$backdatedReservations) {
            if ($bookingCode !== '') {
                $booking = Booking::where('code', $bookingCode)->when($organizationIds !== null, fn ($query) => $query->whereHas('court.venue', fn ($venues) => $venues->whereIn('organization_id', $organizationIds)))->first();
                if ($booking) {
                    $booking->update(['payment_expires_at' => $targetTime]);
                    $backdatedBookings[] = $booking->code;

                    // Backdate associated owner payment attempt
                    $mapping = LegacyBookingMapping::where('legacy_booking_id', $booking->id)->first();
                    $reservation = $mapping ? Reservation::find($mapping->reservation_id) : Reservation::where('code', $booking->code)->first();
                    if ($reservation) {
                        OwnerPaymentAttempt::where('purpose', 'booking')
                            ->where('purpose_id', $reservation->code)
                            ->update(['expires_at' => $targetTime]);
                        $reservation->update(['hold_expires_at' => $targetTime]);
                        $backdatedReservations[] = $reservation->code;
                    }
                    OwnerPaymentAttempt::where('purpose', 'booking')
                        ->where('purpose_id', (string) $booking->id)
                        ->update(['expires_at' => $targetTime]);
                }
            }
            if ($reservationCode !== '') {
                $reservation = Reservation::where('code', $reservationCode)->when($organizationIds !== null, fn ($query) => $query->whereIn('organization_id', $organizationIds))->first();
                if ($reservation) {
                    $reservation->update(['hold_expires_at' => $targetTime]);
                    $backdatedReservations[] = $reservation->code;

                    OwnerPaymentAttempt::where('purpose', 'reservation')
                        ->where('purpose_id', (string) $reservation->id)
                        ->update(['expires_at' => $targetTime]);
                }
            }

            if ($bookingCode === '' && $reservationCode === '') {
                // Backdate all pending bookings and held reservations
                $pendingBookings = Booking::where('status', 'pending_payment')->when($organizationIds !== null, fn ($query) => $query->whereHas('court.venue', fn ($venues) => $venues->whereIn('organization_id', $organizationIds)))->get();
                foreach ($pendingBookings as $b) {
                    $b->update(['payment_expires_at' => $targetTime]);
                    $backdatedBookings[] = $b->code;
                }

                $heldReservations = Reservation::where('status', 'held')->when($organizationIds !== null, fn ($query) => $query->whereIn('organization_id', $organizationIds))->get();
                foreach ($heldReservations as $r) {
                    $r->update(['hold_expires_at' => $targetTime]);
                    $backdatedReservations[] = $r->code;
                }

                OwnerPaymentAttempt::where('status', 'pending')->when($organizationIds !== null, fn ($query) => $query->whereIn('organization_id', $organizationIds))->update(['expires_at' => $targetTime]);
            }
        });

        // Run the real domain expiration sweep action
        $expiredCount = $sweep->execute(200, $organizationIds);

        $out = [
            'status' => 'success',
            'expired_count' => $expiredCount,
            'backdated_bookings' => $backdatedBookings,
            'backdated_reservations' => $backdatedReservations,
        ];

        $this->output->writeln(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
