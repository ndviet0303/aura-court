<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\MonthlyInvoice;
use App\Models\RecurringOccurrence;
use App\Models\RecurringSeries;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Services\Booking\RecurringSeriesService;
use App\Services\Reservation\PriceQuoteService;
use Carbon\Carbon;
use Database\Seeders\Concerns\FindsFreeCourtTime;
use Database\Seeders\Concerns\PaysVenueOwner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class RecurringSeriesSeeder extends Seeder
{
    use FindsFreeCourtTime;
    use PaysVenueOwner;

    /**
     * [contract no, club, club phone, contact (khachNN), court slug, ISO weekdays, start hour, hours, first month (relative to this month), months, state].
     * active: paid up to the coming period, later periods pending; overdue: this month's invoice went unpaid, so its sessions were released;
     * cancelled: ended early like RecurringReleaseService::cancelSeries, so paid periods stay booked, the unpaid sessions after
     * them were released and nothing was invoiced after the cancellation. Paid invoices were paid to the venue owner's connection.
     */
    private const CONTRACTS = [
        [1, 'CLB Cầu Lông Tân Bình Pro', '0908 123 456', 22, 'san-11-sai-gon-central-bwf', [1, 3], 19, 2, -2, 4, 'active'],
        [2, 'Học Viện Pickleball Trẻ Hà Nội', '0912 345 678', 11, 'san-pickleball-2-dynamic-cau-giay', [6, 7], 8, 2, -1, 3, 'active'],
        [3, 'CLB Tennis Doanh Nhân Westlake', '0988 999 888', 16, 'san-tennis-2-hard-court-tay-ho', [2, 4], 6, 2, -2, 3, 'overdue'],
        [4, 'FC Công Nghệ FPT', '0977 112 233', 19, 'san-bong-da-5v5-a2-quan-ngua', [3], 18, 2, -1, 3, 'active'],
        [5, 'CLB Pickleball Saigon Smashers', '0933 445 566', 2, 'san-pickleball-pmh-2', [2], 18, 2, -2, 4, 'active'],
        [6, 'CLB Cầu Lông Đại Học Y Dược', '0903 667 788', 8, 'san-7-tieu-chuan-quan-ngua', [7], 15, 2, -2, 4, 'cancelled'],
    ];

    public function run(): void
    {
        $timezone = config('app.timezone');
        $now = Carbon::now($timezone)->startOfMinute();
        $thisMonth = $now->copy()->startOfMonth();
        $quotes = app(PriceQuoteService::class);
        $recurring = app(RecurringSeriesService::class);

        foreach (self::CONTRACTS as [$no, $club, $phone, $contactNo, $slug, $weekdays, $hour, $hours, $firstMonth, $months, $state]) {
            $from = $thisMonth->copy()->addMonths($firstMonth);
            $contractCode = sprintf('CTR-%d-%03d', $from->year, $no);
            $contact = User::where('email', sprintf('khach%02d@demo.auracourt.vn', $contactNo))->first();
            $court = Court::with(['venue'])->where('slug', $slug)->first();
            // The contract code is the idempotency key: a seeded contract is never rebuilt or moved.
            if (! $contact || ! $court || RecurringSeries::withoutGlobalScope('tenant_isolation')->where('metadata->contract_code', $contractCode)->exists()) {
                continue;
            }

            $signedAt = $from->copy()->subDays(9 + $no)->setTime(9 + $no, 30);
            // overdue: the hourly sweep after this month's due date released the whole period; cancelled: two days before that
            // due date, releasing only this month's unpaid sessions.
            [$releaseFrom, $releasedAt] = match ($state) {
                'overdue' => [$thisMonth->copy(), $thisMonth->copy()->subDays(7)->addHour()],
                'cancelled' => [$thisMonth->copy(), $thisMonth->copy()->subDays(9)->setTime(10, 0)],
                default => [null, null],
            };
            $plan = fn (Court $candidate, int $at): array => $recurring->generateOccurrences(
                $candidate, 'weekly', $weekdays, $from->copy()->setTime($at, 0), $from->copy()->setTime($at + $hours, 0),
                $from->copy()->addMonths($months)->subMinute(), null
            );
            // Contracts keep one weekly time: move the whole series until every session that stays booked is free.
            [$court, $hour] = $this->firstFit($court, $hour, $hours, function (Court $candidate, int $at) use ($plan, $releaseFrom, $contact): bool {
                foreach ($plan($candidate, $at) as $occurrence) {
                    if (($releaseFrom === null || $occurrence['starts_at']->lt($releaseFrom))
                        && ! $this->isFree($candidate->id, $occurrence['starts_at'], $occurrence['ends_at'], $contact->id)) {
                        return false;
                    }
                }

                return true;
            });

            $minutes = (int) $court->slot_duration;
            $sessions = array_map(function (array $occurrence) use ($court, $quotes, $hours, $minutes, $releaseFrom): array {
                $starts = array_map(fn (int $position): Carbon => $occurrence['starts_at']->copy()->addMinutes($position * $minutes), range(0, intdiv($hours * 60, $minutes) - 1));
                $snapshots = $quotes->priceSnapshots($court, $starts, $minutes);

                return $occurrence + [
                    'slots' => array_map(null, $starts, $snapshots),
                    'amount' => (int) array_sum(array_column($snapshots, 'unit_price')),
                    'released' => $releaseFrom !== null && $occurrence['starts_at']->gte($releaseFrom),
                ];
            }, $plan($court, $hour));

            // One invoice per calendar month, due 7 days before the period like RecurringSeriesService::createInvoices.
            $invoices = collect($sessions)->groupBy(fn (array $session): string => $session['starts_at']->format('Y-m'))
                ->map(function ($monthSessions, string $month) use ($state, $thisMonth, $now, $releasedAt, $signedAt, $no): ?array {
                    $periodStart = Carbon::createFromFormat('Y-m-d H:i:s', $month.'-01 00:00:00', $thisMonth->timezone);
                    $dueAt = $periodStart->copy()->subDays(7);
                    $status = match (true) {
                        $state === 'cancelled' && $dueAt->gte($releasedAt) => null,
                        $state === 'overdue' && $periodStart->gte($thisMonth) => 'overdue',
                        // Pending only while comfortably ahead of the hourly overdue sweep; nearer periods were paid early.
                        $dueAt->gt($now->copy()->addDays(10)) => 'pending',
                        default => 'paid',
                    };

                    return $status === null ? null : [
                        'period_start' => $periodStart,
                        'due_at' => $dueAt,
                        'amount' => (int) $monthSessions->sum('amount'),
                        'status' => $status,
                        'paid_at' => $status === 'paid'
                            ? $dueAt->copy()->subDays(3)->addHours(9 + $no)->max($signedAt->copy()->addHours(2))->min($now->copy()->subHour())
                            : null,
                    ];
                })->filter();
            $monthlyAmount = (int) $invoices->max('amount');

            $series = RecurringSeries::forceCreate([
                'organization_id' => $court->venue->organization_id,
                'user_id' => $contact->id,
                'court_id' => $court->id,
                'frequency' => 'weekly',
                'starts_at' => $sessions[0]['starts_at'],
                // Both contract screens read ends_at as the contract end date; its time of day is still the session end.
                'ends_at' => end($sessions)['ends_at'],
                'series_ends_at' => end($sessions)['ends_at'],
                'occurrence_count' => count($sessions),
                'status' => $state === 'cancelled' ? 'cancelled' : 'confirmed',
                'total_amount' => array_sum(array_column($sessions, 'amount')),
                'next_invoice_due_at' => $invoices->where('status', 'pending')->min('due_at'),
                'metadata' => [
                    'horizon' => end($sessions)['starts_at']->toIso8601String(),
                    'contract_code' => $contractCode,
                    'customer_name' => $club,
                    'phone' => $phone,
                    'weekday' => $this->weekdayLabel($weekdays),
                    'time_slot' => sprintf('%02d:00 - %02d:00', $hour, $hour + $hours),
                    'monthly_amount' => $monthlyAmount,
                    'deposit_amount' => (int) ceil($monthlyAmount / 500000) * 500000,
                    ...($state === 'cancelled' ? [
                        'cancelled_at' => $releasedAt->toIso8601String(),
                        'cancel_reason' => 'CLB tạm ngưng sinh hoạt trong mùa thi học kỳ, hai bên thống nhất thanh lý hợp đồng.',
                    ] : []),
                ],
                'created_at' => $signedAt,
                'updated_at' => $releasedAt ?? $signedAt,
            ]);

            foreach ($sessions as $index => $session) {
                $released = $session['released'];
                $changedAt = $released ? $releasedAt : $signedAt;
                // Shaped like RecurringSeriesService's quote/hold/confirm (no wallet debit; the contract is billed monthly).
                $reservation = Reservation::query()->forceCreateQuietly([
                    'organization_id' => $series->organization_id,
                    'user_id' => $contact->id,
                    'court_id' => $court->id,
                    'code' => sprintf('RES-REC-%03d-%02d', $no, $index + 1),
                    'status' => $released ? 'cancelled' : 'confirmed',
                    'currency' => 'VND',
                    'total_amount' => $session['amount'],
                    'confirmed_at' => $signedAt,
                    'cancelled_at' => $released ? $releasedAt : null,
                    'payment_model' => 'direct_owner_v1',
                    'metadata' => ['type' => 'recurring_series', 'series_id' => $series->id],
                    'created_at' => $signedAt,
                    'updated_at' => $changedAt,
                ]);
                foreach ($session['slots'] as [$slotStart, $snapshot]) {
                    $slot = ReservationSlot::forceCreate([
                        'organization_id' => $series->organization_id,
                        'reservation_id' => $reservation->id,
                        'court_id' => $court->id,
                        'starts_at' => $slotStart,
                        'ends_at' => $slotStart->copy()->addMinutes($minutes),
                        'unit_price' => $snapshot['unit_price'],
                        'status' => $released ? 'cancelled' : 'confirmed',
                        'snapshot' => $snapshot,
                        'created_at' => $signedAt,
                        'updated_at' => $changedAt,
                    ]);
                    ReservationEntitlement::forceCreate([
                        'organization_id' => $series->organization_id,
                        'reservation_id' => $reservation->id,
                        'reservation_slot_id' => $slot->id,
                        'user_id' => $contact->id,
                        'court_id' => $court->id,
                        'starts_at' => $slotStart,
                        'ends_at' => $slotStart->copy()->addMinutes($minutes),
                        'amount' => $snapshot['unit_price'],
                        'status' => $released ? 'cancelled' : 'active',
                        'created_at' => $signedAt,
                        'updated_at' => $changedAt,
                    ]);
                }
                RecurringOccurrence::forceCreate([
                    'organization_id' => $series->organization_id,
                    'recurring_series_id' => $series->id,
                    'reservation_id' => $reservation->id,
                    'sequence' => $index + 1,
                    'starts_at' => $session['starts_at'],
                    'ends_at' => $session['ends_at'],
                    'amount' => $session['amount'],
                    'status' => $released ? 'released' : 'confirmed',
                    'released_at' => $released ? $releasedAt : null,
                    'created_at' => $signedAt,
                    'updated_at' => $changedAt,
                ]);
            }

            foreach ($invoices as $month => $invoice) {
                $reference = $invoice['paid_at'] ? 'AC'.Str::upper(Str::random(18)) : null;
                $monthly = MonthlyInvoice::forceCreate([
                    'uuid' => Uuid::uuid5(Uuid::NAMESPACE_URL, "auracourt-demo/invoice/{$contractCode}/{$month}")->toString(),
                    'organization_id' => $series->organization_id,
                    'recurring_series_id' => $series->id,
                    'period_start' => $invoice['period_start'],
                    'period_end' => $invoice['period_start']->copy()->endOfMonth(),
                    'due_at' => $invoice['due_at'],
                    'amount' => $invoice['amount'],
                    'status' => $invoice['status'],
                    'payment_reference' => $reference,
                    'paid_at' => $invoice['paid_at'],
                    'overdue_at' => $invoice['status'] === 'overdue' ? $releasedAt : null,
                    'created_at' => $signedAt,
                    'updated_at' => $invoice['paid_at'] ?? ($invoice['status'] === 'overdue' ? $releasedAt : $signedAt),
                ]);
                if ($reference) {
                    $this->payOwner($court->venue, $contact, 'recurring_period', "rec_series:{$series->id}:invoice:{$monthly->id}", $invoice['amount'], $invoice['paid_at']->copy()->subMinutes(4), $invoice['paid_at'], $reference);
                }
            }
        }
    }

    /** ISO weekdays as the contract screens print them: "Thứ 2, 4", "Thứ 7, Chủ Nhật", "Chủ Nhật". */
    private function weekdayLabel(array $weekdays): string
    {
        $numbers = array_map(fn (int $day): int => $day + 1, array_values(array_filter($weekdays, fn (int $day): bool => $day < 7)));
        $label = $numbers === [] ? '' : 'Thứ '.implode(', ', $numbers);

        return in_array(7, $weekdays, true) ? ltrim($label.', Chủ Nhật', ', ') : $label;
    }
}
