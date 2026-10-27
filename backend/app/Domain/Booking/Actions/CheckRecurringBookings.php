<?php

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\DTOs\OccurrenceRefusal;
use App\Domain\Booking\DTOs\RecurringBookingData;
use App\Domain\Promotion\DTOs\VoucherQuoteData;
use App\Exceptions\VoucherNoLongerApplies;
use App\Models\User;
use App\Services\Promotion\VoucherChoiceService;
use App\Services\Reservation\PriceQuoteService;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * FR-136, FR-137, BR-169..BR-172: checks each occurrence on its own with the ordinary booking checks (a price quote:
 * service hours, blocks, live bookings and holds, price) and lists the vouchers that apply to it, as the booking
 * form does. A refused occurrence says why; none is moved to another day, time or court. Nothing is held.
 */
class CheckRecurringBookings
{
    public function __construct(
        private readonly PriceQuoteService $quotes,
        private readonly VoucherChoiceService $choices,
    ) {}

    /** @return list<array<string, mixed>> */
    public function execute(User $user, int $organizationId, RecurringBookingData $data): array
    {
        $rows = [];
        foreach ($data->occurrences as $occurrence) {
            $row = ['starts_at' => $occurrence['starts_at'], 'ends_at' => $occurrence['ends_at']];
            try {
                $this->quotes->create($user->id, $organizationId, $data->courtId, $occurrence['starts_at'], $occurrence['ends_at']);
                $interval = fn (?string $code): VoucherQuoteData => new VoucherQuoteData(
                    $data->courtId,
                    CarbonImmutable::parse($occurrence['starts_at']),
                    CarbonImmutable::parse($occurrence['ends_at']),
                    $code,
                );
                $choice = $this->choices->applicable($interval(null));
                $row += ['rental' => $choice['rental'], 'vouchers' => $choice['vouchers']];
                if (! empty($occurrence['voucher_code'])) {
                    try {
                        $row['code_voucher'] = $this->choices->check($interval($occurrence['voucher_code']));
                    } catch (VoucherNoLongerApplies $e) {
                        $row['code_refusal'] = OccurrenceRefusal::from($e);
                    }
                }
            } catch (Throwable $e) {
                $row['refusal'] = OccurrenceRefusal::from($e);
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
