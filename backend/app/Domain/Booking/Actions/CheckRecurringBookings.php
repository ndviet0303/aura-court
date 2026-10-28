<?php

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\DTOs\OccurrenceRefusal;
use App\Domain\Booking\DTOs\RecurringBookingData;
use App\Domain\Promotion\DTOs\VoucherQuoteData;
use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use App\Exceptions\VoucherNoLongerApplies;
use App\Models\User;
use App\Services\Promotion\VoucherChoiceService;
use App\Services\Reservation\PriceQuoteService;
use App\Services\Reservation\SlotSelectionPolicy;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * FR-135..FR-137, BR-169..BR-172: checks each occurrence on its own with the ordinary booking checks (the venue's
 * booking rules the checkout applies: started times, book-ahead horizon, duration bounds; then a price quote: service
 * hours, blocks, live bookings and holds, price) and lists the vouchers that apply to it, as the booking
 * form does. A refused occurrence says why; none is moved to another day, time or court. Nothing is held.
 */
class CheckRecurringBookings
{
    public function __construct(
        private readonly PriceQuoteService $quotes,
        private readonly VoucherChoiceService $choices,
        private readonly SlotSelectionPolicy $slotPolicy,
        private readonly VerifyEffectiveBookingEligibility $eligibility,
    ) {}

    /** @return list<array<string, mixed>> */
    public function execute(User $user, int $organizationId, RecurringBookingData $data): array
    {
        $rows = [];
        $court = null;
        foreach ($data->occurrences as $occurrence) {
            $row = ['starts_at' => $occurrence['starts_at'], 'ends_at' => $occurrence['ends_at']];
            try {
                $court ??= $this->eligibility->assertEligible($data->courtId, publicFacing: true);
                $this->slotPolicy->assertAllowed($court, [['start' => $occurrence['starts_at'], 'end' => $occurrence['ends_at']]]);
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

        return $this->flagSharedCodes($rows, $data);
    }

    /**
     * UC-8.6 E-3, BR-170: a private code is single-use, so one named on several occurrences is a conflict on each of
     * them for the customer to resolve; it is never counted as usable more than once.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function flagSharedCodes(array $rows, RecurringBookingData $data): array
    {
        // Codes are matched after trim and upper case, as VoucherEligibility looks them up.
        $codes = array_map(fn (array $occurrence): string => strtoupper(trim((string) ($occurrence['voucher_code'] ?? ''))), $data->occurrences);
        $counts = array_count_values(array_filter($codes));
        foreach ($rows as $i => $row) {
            if (($counts[$codes[$i]] ?? 0) > 1 && ! isset($row['refusal'])) {
                unset($rows[$i]['code_voucher']);
                $rows[$i]['code_refusal'] ??= OccurrenceRefusal::codeShared();
            }
        }

        return $rows;
    }
}
