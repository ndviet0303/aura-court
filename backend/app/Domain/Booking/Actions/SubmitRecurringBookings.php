<?php

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\DTOs\OccurrenceRefusal;
use App\Domain\Booking\DTOs\RecurringBookingData;
use App\Models\User;
use App\Services\Booking\OrdinaryCheckoutService;
use App\Services\Reservation\ReservationIntake;
use Illuminate\Support\Str;
use Throwable;

/**
 * FR-138, BR-173..BR-175: each occurrence becomes one ordinary booking through the ordinary checkout, at the court,
 * day and time the customer chose and at most the amount they were shown (409 PRICE_CHANGED otherwise). Each booking
 * keeps its own code, deadline, payment and voucher; a refused occurrence leaves the others as they are, and no
 * occurrence is added or moved.
 */
class SubmitRecurringBookings
{
    public function __construct(
        private readonly OrdinaryCheckoutService $checkout,
        private readonly ReservationIntake $intake,
    ) {}

    /** @return list<array<string, mixed>> */
    public function execute(User $user, int $organizationId, RecurringBookingData $data): array
    {
        // Account checks once, before any occurrence, rather than one refusal per row.
        abort_if($user->isRestricted(), 403, 'Tài khoản của bạn đã bị khóa hoặc hạn chế quyền truy cập.');
        $this->intake->assertBookerPhone($user);

        // Module 08.2 D7: one group per submit, so a transfer can offer every remaining occurrence (BR-179).
        $group = (string) Str::uuid();
        $rows = [];
        foreach ($data->occurrences as $occurrence) {
            $row = ['starts_at' => $occurrence['starts_at'], 'ends_at' => $occurrence['ends_at']];
            try {
                $row['reservation'] = $this->checkout->checkout($user, $organizationId, [
                    'court_id' => $data->courtId,
                    'starts_at' => $occurrence['starts_at'],
                    'ends_at' => $occurrence['ends_at'],
                    'expected_total' => $occurrence['expected_total'],
                    'metadata' => ['recurring_group' => $group],
                    ...array_filter([
                        'voucher_program_id' => $occurrence['voucher_program_id'] ?? null,
                        'voucher_code' => $occurrence['voucher_code'] ?? null,
                    ], fn ($value): bool => $value !== null && $value !== ''),
                ]);
            } catch (Throwable $e) {
                $row['refusal'] = OccurrenceRefusal::from($e);
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
