<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\CourtBlock;
use App\Models\CourtPriceSlot;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $timezone = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $now = Carbon::now($timezone);

        $admin = User::firstOrNew(['email' => env('SEED_ADMIN_EMAIL', 'admin@example.com')]);
        $admin->forceFill([
            'name' => 'Quản trị viên',
            'password' => Hash::make(env('SEED_ADMIN_PASSWORD', 'ChangeMe123!')),
            'role' => 'admin',
            'email_verified_at' => $now,
        ])->save();

        $demoAdmin = User::firstOrNew(['email' => 'admin-demo@demo.auracourt.vn']);
        $demoAdmin->forceFill([
            'name' => 'Quản trị demo',
            'password' => Hash::make('Demo12345!'),
            'role' => 'admin',
            'email_verified_at' => $now,
        ])->save();

        $customers = collect(range(1, 24))->map(function (int $number) use ($now): User {
            $user = User::firstOrNew(['email' => sprintf('khach%02d@demo.auracourt.vn', $number)]);
            $user->forceFill([
                'name' => sprintf('Khách hàng %02d', $number),
                'password' => Hash::make('Demo12345!'),
                'role' => 'customer',
                'email_verified_at' => $now->copy()->subDays($number),
            ])->save();

            return $user;
        });

        $courtNames = [
            'Sân 1', 'Sân 2', 'Sân 3', 'Sân 4',
            'Sân 5 VIP', 'Sân 6 VIP', 'Sân 7 Đôi', 'Sân 8 Đôi',
        ];
        $courts = collect();
        $timestamp = $now->toDateTimeString();

        foreach ($courtNames as $index => $name) {
            $court = Court::updateOrCreate(
                ['name' => $name],
                [
                    'description' => $index >= 4
                        ? 'Sân cao cấp, không gian rộng và phù hợp thi đấu đôi.'
                        : 'Sân cầu lông tiêu chuẩn, ánh sáng tốt và mặt sân êm.',
                    'image_url' => sprintf('/aura/court-%d.jpg', ($index % 6) + 1),
                    'is_active' => true,
                ],
            );
            $courts->push($court);

            $priceRows = [];
            for ($weekday = 1; $weekday <= 7; $weekday++) {
                for ($hour = 6; $hour < 23; $hour++) {
                    $isWeekend = $weekday >= 6;
                    $isPeak = $hour >= 17 && $hour < 22;
                    $premium = $index >= 4;
                    $price = 100000 + ($isWeekend ? 20000 : 0) + ($isPeak ? 30000 : 0) + ($premium ? 20000 : 0);
                    $startsAt = sprintf('%02d:00', $hour);

                    $priceRows[] = [
                        'court_id' => $court->id,
                        'weekday' => $weekday,
                        'starts_at' => $startsAt,
                        'ends_at' => sprintf('%02d:00', $hour + 1),
                        'price' => $price,
                        'is_active' => true,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            }
            CourtPriceSlot::upsert($priceRows, ['court_id', 'weekday', 'starts_at'], ['ends_at', 'price', 'is_active', 'updated_at']);
        }

        $blockStart = $now->copy()->startOfDay()->addDays(60)->setTime(10, 0);
        foreach ([1, 4, 6] as $courtIndex) {
            $court = $courts[$courtIndex];
            CourtBlock::updateOrCreate(
                ['court_id' => $court->id, 'starts_at' => $blockStart->copy()->utc()->format('Y-m-d H:i:s')],
                [
                    'ends_at' => $blockStart->copy()->addHours(2)->utc()->format('Y-m-d H:i:s'),
                    'reason' => $courtIndex === 4 ? 'Bảo trì mặt sân' : 'Tổ chức giải nội bộ',
                ],
            );
        }

        foreach (range(1, 48) as $number) {
            $court = $courts[($number - 1) % $courts->count()];
            $customer = $customers[($number - 1) % $customers->count()];
            $dayOffset = 2 + intdiv($number - 1, $courts->count());
            $hour = 6 + ((($number - 1) % 8) * 2);
            $slotCount = $number % 4 === 0 ? 2 : 1;
            $starts = collect(range(0, $slotCount - 1))->map(
                fn (int $offset) => $now->copy()->startOfDay()->addDays($dayOffset)->setTime($hour + $offset, 0),
            );

            $status = match ($number % 10) {
                2 => 'cancelled',
                3 => 'expired',
                4 => 'refund_pending',
                5 => 'refunded',
                7 => 'no_show',
                default => $number % 10 === 1 ? 'pending_payment' : 'confirmed',
            };
            $isHistorical = in_array($status, ['cancelled', 'expired', 'refund_pending', 'refunded', 'no_show'], true);
            if ($isHistorical) {
                $starts = $starts->map(fn (Carbon $start) => $start->subDays(30 + ($number % 10)));
            }

            $prices = $starts->map(function (Carbon $start) use ($court, $courts): int {
                $courtIndex = $courts->search(fn (Court $item): bool => $item->id === $court->id);
                $isWeekend = $start->dayOfWeekIso >= 6;
                $isPeak = $start->hour >= 17 && $start->hour < 22;
                $premium = $courtIndex >= 4;

                return 100000 + ($isWeekend ? 20000 : 0) + ($isPeak ? 30000 : 0) + ($premium ? 20000 : 0);
            });
            $total = $prices->sum();
            $code = sprintf('DEMO-BK-%03d', $number);
            $booking = Booking::updateOrCreate(
                ['code' => $code],
                [
                    'user_id' => $customer->id,
                    'court_id' => $court->id,
                    'total_amount' => $total,
                    'status' => $status,
                    'payment_expires_at' => $status === 'pending_payment' ? $now->copy()->addMinutes(15) : ($status === 'expired' ? $now->copy()->subDay() : null),
                    'cancelled_at' => $isHistorical && $status !== 'no_show' ? $now->copy()->subDays(2) : null,
                    'cancellation_reason' => $isHistorical && $status !== 'no_show' ? 'Dữ liệu demo' : null,
                ],
            );

            foreach ($starts as $slotIndex => $start) {
                $slotStatus = match ($status) {
                    'pending_payment' => 'held',
                    'confirmed', 'no_show' => 'confirmed',
                    'expired' => 'expired',
                    default => 'cancelled',
                };
                if ($number % 12 === 0 && $slotIndex === 1 && $status === 'confirmed') {
                    $slotStatus = 'cancelled';
                }

                BookingSlot::updateOrCreate(
                    ['booking_id' => $booking->id, 'starts_at' => $start->copy()->utc()->format('Y-m-d H:i:s')],
                    [
                        'court_id' => $court->id,
                        'ends_at' => $start->copy()->addHour()->utc()->format('Y-m-d H:i:s'),
                        'unit_price' => $prices[$slotIndex],
                        'status' => $slotStatus,
                    ],
                );
            }

            $paymentStatus = match ($status) {
                'confirmed', 'no_show', 'refunded' => 'paid',
                'pending_payment' => 'manual_review',
                default => 'rejected',
            };
            Payment::updateOrCreate(
                ['external_transaction_id' => sprintf('DEMO-TX-%03d', $number)],
                [
                    'booking_id' => $booking->id,
                    'provider' => 'sepay',
                    'amount' => $total,
                    'transfer_code' => $code,
                    'status' => $paymentStatus,
                    'payload' => ['demo' => true, 'source' => 'DatabaseSeeder'],
                    'received_at' => $paymentStatus === 'manual_review' ? null : $now->copy()->subDays(1),
                ],
            );

            if ($status === 'refund_pending' || $status === 'refunded') {
                Refund::updateOrCreate(
                    ['booking_id' => $booking->id],
                    [
                        'amount' => $total,
                        'status' => $status === 'refunded' ? 'processed' : 'pending',
                        'note' => 'Hoàn tiền dữ liệu demo',
                        'processed_by' => $status === 'refunded' ? $admin->id : null,
                        'processed_at' => $status === 'refunded' ? $now->copy()->subDays(1) : null,
                    ],
                );
            }

            if ($number % 12 === 0 && $status === 'confirmed') {
                $partialAmount = (int) $prices[1];
                Refund::updateOrCreate(
                    ['booking_id' => $booking->id],
                    ['amount' => $partialAmount, 'status' => 'pending', 'note' => 'Khách hủy một khung giờ demo'],
                );
            }
        }
    }
}
