<?php

namespace App\Services;

use App\Models\MonthlyInvoice;
use App\Models\RecurringOccurrence;
use App\Models\RecurringSeries;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurringSeriesService
{
    public function __construct(
        private readonly PriceQuoteService $quotes,
        private readonly ReservationService $reservations,
        private readonly OutboxService $outbox,
    ) {}

    public function createAndConfirm(User $user, int $organizationId, array $data): RecurringSeries
    {
        $frequency = strtolower((string) ($data['frequency'] ?? ''));
        if (! in_array($frequency, ['weekly', 'monthly'], true)) {
            throw ValidationException::withMessages(['frequency' => 'Frequency must be weekly or monthly.']);
        }
        $firstStart = Carbon::parse($data['starts_at'], config('app.timezone'))->setMinute(0)->setSecond(0);
        $firstEnd = isset($data['ends_at']) ? Carbon::parse($data['ends_at'], config('app.timezone')) : $firstStart->copy()->addHour();
        $horizon = isset($data['until']) ? Carbon::parse($data['until'], config('app.timezone')) : $firstStart->copy()->addMonths(12);
        if ($horizon->greaterThan($firstStart->copy()->addMonths(12))) {
            throw ValidationException::withMessages(['until' => 'A recurring series may not exceed a 12-month horizon.']);
        }
        $starts = $this->occurrenceStarts($firstStart, $horizon, $frequency, (int) ($data['occurrences'] ?? 0));
        if (count($starts) > 104) {
            throw ValidationException::withMessages(['occurrences' => 'A recurring series may not exceed 104 occurrences.']);
        }

        return DB::transaction(function () use ($user, $organizationId, $data, $frequency, $firstStart, $firstEnd, $starts): RecurringSeries {
            $series = RecurringSeries::create([
                'organization_id' => $organizationId,
                'user_id' => $user->id,
                'court_id' => (int) $data['court_id'],
                'frequency' => $frequency,
                'starts_at' => $firstStart,
                'ends_at' => $firstEnd,
                'occurrence_count' => count($starts),
                'status' => 'draft',
                'metadata' => ['horizon' => end($starts)->toIso8601String()],
            ]);
            $total = 0;
            foreach ($starts as $sequence => $start) {
                $quote = $this->quotes->create($user->id, $organizationId, $series->court_id, [$start->toIso8601String()]);
                $reservation = $this->reservations->hold($user, $organizationId, ['quote_id' => $quote->id]);
                $reservation = $this->reservations->confirm($reservation);
                $amount = (int) $reservation->total_amount;
                $total += $amount;
                RecurringOccurrence::create([
                    'organization_id' => $organizationId,
                    'recurring_series_id' => $series->id,
                    'reservation_id' => $reservation->id,
                    'sequence' => $sequence + 1,
                    'starts_at' => $start,
                    'ends_at' => $start->copy()->add($firstEnd->diffAsCarbonInterval($firstStart)),
                    'amount' => $amount,
                    'status' => 'confirmed',
                ]);
            }
            $series->update(['status' => 'confirmed', 'total_amount' => $total, 'next_invoice_due_at' => $firstStart->copy()->subDays(7)]);
            $this->createInvoices($series->fresh('occurrences'));
            return $series->fresh(['occurrences.reservation', 'invoices']);
        });
    }

    public function payInvoice(MonthlyInvoice $invoice, string $paymentReference): MonthlyInvoice
    {
        abort_unless($invoice->status === 'pending', 409, 'Invoice is not payable.');
        $invoice->update(['status' => 'paid', 'payment_reference' => $paymentReference, 'paid_at' => now()]);
        return $invoice->fresh();
    }

    public function markOverdueAndReleaseFuture(): int
    {
        $released = 0;
        MonthlyInvoice::query()->where('status', 'pending')->where('due_at', '<', now())->each(function (MonthlyInvoice $invoice) use (&$released): void {
            DB::transaction(function () use ($invoice, &$released): void {
                $invoice->update(['status' => 'overdue', 'overdue_at' => now()]);
                $occurrences = $invoice->series->occurrences()->where('starts_at', '>', now())->whereIn('status', ['held', 'confirmed'])->get();
                foreach ($occurrences as $occurrence) {
                    $occurrence->reservation?->update(['status' => 'cancelled', 'cancelled_at' => now()]);
                    $occurrence->reservation?->slots()->whereIn('status', ['held', 'confirmed'])->update(['status' => 'cancelled']);
                    $occurrence->reservation?->entitlements()->whereIn('status', ['held', 'active'])->update(['status' => 'cancelled']);
                    $occurrence->update(['status' => 'released', 'released_at' => now()]);
                    $released++;
                }
                $invoice->series->update(['status' => 'delinquent']);
            });
        });
        return $released;
    }

    public function notifyUpcomingInvoices(): int
    {
        $count = 0;
        MonthlyInvoice::query()->with('series.user')->where('status', 'pending')->whereNull('reminder_sent_at')->whereBetween('due_at', [now()->addDays(7), now()->addDays(8)])->each(function (MonthlyInvoice $invoice) use (&$count): void {
            $invoice->update(['reminder_sent_at' => now()]);
            $this->outbox->enqueue([
                'organization_id' => $invoice->organization_id,
                'message_key' => "recurring-invoice:{$invoice->id}:reminder",
                'channel' => 'notification',
                'event_type' => 'recurring.invoice_due',
                'aggregate_type' => MonthlyInvoice::class,
                'aggregate_id' => $invoice->id,
            ], ['user_id' => $invoice->series->user_id, 'notification' => ['event' => 'recurring_invoice_due', 'invoice_id' => $invoice->id, 'amount' => $invoice->amount]]);
            $count++;
        });
        return $count;
    }

    private function occurrenceStarts(Carbon $first, Carbon $horizon, string $frequency, int $requested): array
    {
        $starts = [];
        $current = $first->copy();
        while ($current->lessThanOrEqualTo($horizon) && count($starts) < 105) {
            $starts[] = $current->copy();
            if ($requested > 0 && count($starts) >= $requested) {
                break;
            }
            $current = $frequency === 'weekly' ? $current->addWeek() : $current->addMonthNoOverflow();
        }
        return $starts;
    }

    private function createInvoices(RecurringSeries $series): void
    {
        $groups = $series->occurrences->groupBy(fn (RecurringOccurrence $occurrence) => $occurrence->starts_at->format('Y-m'));
        foreach ($groups as $month => $occurrences) {
            $periodStart = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
            MonthlyInvoice::create([
                'organization_id' => $series->organization_id,
                'recurring_series_id' => $series->id,
                'period_start' => $periodStart,
                'period_end' => $periodStart->copy()->endOfMonth(),
                'due_at' => $periodStart->copy()->subDays(7),
                'amount' => $occurrences->sum('amount'),
                'status' => 'pending',
            ]);
        }
    }
}
