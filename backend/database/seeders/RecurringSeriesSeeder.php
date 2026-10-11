<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\MonthlyInvoice;
use App\Models\RecurringOccurrence;
use App\Models\RecurringSeries;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RecurringSeriesSeeder extends Seeder
{
    public function run(): void
    {
        $timezone = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $now = Carbon::now($timezone);

        $courts = Court::with('venue')->where('is_active', true)->get();
        if ($courts->isEmpty()) {
            return;
        }

        // Clean up previous test seed series if needed or ensure fresh realistic contracts
        RecurringSeries::query()->withoutGlobalScope('tenant_isolation')->delete();

        $sampleContracts = [
            [
                'customer_name' => 'CLB Cầu Lông Tân Bình Pro',
                'phone' => '0908 123 456',
                'court_keyword' => 'san-1',
                'weekday' => 'Thứ 2, 4, 6',
                'time_slot' => '18:00 - 20:00',
                'months_ahead' => 6,
                'monthly_amount' => 4800000,
                'deposit_amount' => 5000000,
                'status' => 'confirmed',
                'has_overdue' => false,
            ],
            [
                'customer_name' => 'Học Viện Pickleball Trẻ Hà Nội',
                'phone' => '0912 345 678',
                'court_keyword' => 'san-2',
                'weekday' => 'Thứ 3, 5, 7',
                'time_slot' => '17:00 - 19:00',
                'months_ahead' => 12,
                'monthly_amount' => 5600000,
                'deposit_amount' => 6000000,
                'status' => 'confirmed',
                'has_overdue' => false,
            ],
            [
                'customer_name' => 'CLB Tennis Doanh Nhân Westlake',
                'phone' => '0988 999 888',
                'court_keyword' => 'san-3',
                'weekday' => 'Thứ 7, Chủ Nhật',
                'time_slot' => '06:00 - 08:00',
                'months_ahead' => 6,
                'monthly_amount' => 6200000,
                'deposit_amount' => 6000000,
                'status' => 'overdue',
                'has_overdue' => true,
            ],
            [
                'customer_name' => 'Nhóm Cầu Lông Công Nghệ FPT',
                'phone' => '0977 112 233',
                'court_keyword' => 'san-4',
                'weekday' => 'Thứ 3, 5',
                'time_slot' => '19:00 - 21:00',
                'months_ahead' => 3,
                'monthly_amount' => 3600000,
                'deposit_amount' => 4000000,
                'status' => 'confirmed',
                'has_overdue' => false,
            ],
            [
                'customer_name' => 'CLB Pickleball Saigon Smashers',
                'phone' => '0933 445 566',
                'court_keyword' => 'san-5',
                'weekday' => 'Thứ 2, 6',
                'time_slot' => '18:30 - 20:30',
                'months_ahead' => 6,
                'monthly_amount' => 4200000,
                'deposit_amount' => 5000000,
                'status' => 'confirmed',
                'has_overdue' => false,
            ],
            [
                'customer_name' => 'CLB Cầu Lông Đại Học Y Dược',
                'phone' => '0903 667 788',
                'court_keyword' => 'san-6',
                'weekday' => 'Chủ Nhật',
                'time_slot' => '15:00 - 18:00',
                'months_ahead' => 4,
                'monthly_amount' => 2800000,
                'deposit_amount' => 3000000,
                'status' => 'cancelled',
                'has_overdue' => false,
            ],
        ];

        foreach ($sampleContracts as $idx => $sc) {
            $court = $courts->first(fn ($c) => str_contains($c->slug, $sc['court_keyword'])) ?? $courts->get($idx % $courts->count());
            $orgId = $court->venue?->organization_id ?: 1;

            $user = User::firstOrCreate(
                ['phone' => $sc['phone']],
                [
                    'name' => $sc['customer_name'],
                    'email' => 'contract_user_'.($idx + 1).'@auracontrol.vn',
                    'password' => bcrypt('password123'),
                    'role' => 'customer',
                    'email_verified_at' => $now,
                ]
            );

            $startsAt = $now->copy()->subMonth()->startOfMonth();
            $endsAt = $startsAt->copy()->addMonths($sc['months_ahead']);
            $monthsCount = $sc['months_ahead'];
            $totalAmount = $sc['monthly_amount'] * $monthsCount;

            $series = RecurringSeries::create([
                'organization_id' => $orgId,
                'user_id' => $user->id,
                'court_id' => $court->id,
                'frequency' => 'weekly',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'occurrence_count' => $monthsCount * 4,
                'max_occurrences' => 104,
                'status' => $sc['status'],
                'total_amount' => $totalAmount,
                'next_invoice_due_at' => $now->copy()->addDays(5),
                'metadata' => [
                    'contract_code' => 'CTR-2026-00'.($idx + 1),
                    'customer_name' => $sc['customer_name'],
                    'phone' => $sc['phone'],
                    'deposit_amount' => $sc['deposit_amount'],
                    'monthly_amount' => $sc['monthly_amount'],
                    'weekday' => $sc['weekday'],
                    'time_slot' => $sc['time_slot'],
                ],
            ]);

            // Create 8 sample weekly occurrences
            for ($seq = 1; $seq <= min(12, $monthsCount * 4); $seq++) {
                $occStart = $startsAt->copy()->addWeeks($seq - 1)->setHour(18)->setMinute(0)->setSecond(0);
                RecurringOccurrence::create([
                    'organization_id' => $orgId,
                    'recurring_series_id' => $series->id,
                    'sequence' => $seq,
                    'starts_at' => $occStart,
                    'ends_at' => $occStart->copy()->addHours(2),
                    'amount' => (int) round($sc['monthly_amount'] / 4),
                    'status' => $sc['status'] === 'cancelled' && $occStart->isFuture() ? 'released' : 'confirmed',
                ]);
            }

            // Create monthly invoices
            for ($m = 0; $m < min(4, $monthsCount); $m++) {
                $periodStart = $startsAt->copy()->addMonths($m)->startOfMonth();
                $periodEnd = $periodStart->copy()->endOfMonth();
                $dueAt = $periodStart->copy()->subDays(5);

                $invoiceStatus = 'pending';
                $paidAt = null;
                $payRef = null;

                if ($m === 0) {
                    $invoiceStatus = 'paid';
                    $paidAt = $dueAt->copy()->subDay();
                    $payRef = 'REC-2026-'.strtoupper(Str::random(6));
                } elseif ($m === 1) {
                    if ($sc['has_overdue']) {
                        $invoiceStatus = 'overdue';
                    } else {
                        $invoiceStatus = 'paid';
                        $paidAt = $dueAt->copy();
                        $payRef = 'REC-2026-'.strtoupper(Str::random(6));
                    }
                } else {
                    $invoiceStatus = 'pending';
                }

                MonthlyInvoice::create([
                    'organization_id' => $orgId,
                    'recurring_series_id' => $series->id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'due_at' => $dueAt,
                    'amount' => $sc['monthly_amount'],
                    'status' => $invoiceStatus,
                    'payment_reference' => $payRef,
                    'paid_at' => $paidAt,
                    'uuid' => (string) Str::uuid(),
                ]);
            }
        }
    }
}
