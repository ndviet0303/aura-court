<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Review;
use App\Models\User;
use App\Services\Reservation\LegacyBookingBackfillService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BookingPaymentSeeder extends Seeder
{
    public function run(): void
    {
        $timezone = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $now = Carbon::now($timezone);

        $admin = User::where('role', 'admin')->first();
        $courts = Court::all();
        $customers = User::where('role', 'customer')->get();

        if ($courts->isEmpty() || $customers->isEmpty()) {
            return;
        }

        $backfill = app(LegacyBookingBackfillService::class);
        $createdBookings = [];
        $operatingHours = [6, 7, 8, 9, 10, 11, 13, 14, 15, 16, 17, 18, 19, 20, 21];

        foreach (range(1, 72) as $number) {
            $code = sprintf('DEMO-BK-%03d', $number);
            // Never rewrite a seeded booking: a changed booking would no longer match its reservation mapping.
            if (Booking::where('code', $code)->exists()) {
                continue;
            }

            $court = $courts[($number - 1) % $courts->count()];
            $customer = $customers[($number - 1) % $customers->count()];

            // Distribute across today (1..20), past 7 days (21..42), future days (43..58), past weeks (59..72)
            if ($number <= 20) {
                $dayOffset = 0; // Today
                $hour = $operatingHours[($number - 1) % count($operatingHours)];
            } elseif ($number <= 42) {
                $dayOffset = -(1 + (($number - 21) % 6)); // Past 1 to 6 days
                $hour = $operatingHours[($number * 3) % count($operatingHours)];
            } elseif ($number <= 58) {
                $dayOffset = 1 + (($number - 43) % 12); // Future days in this month
                $hour = $operatingHours[($number * 2) % count($operatingHours)];
            } else {
                $dayOffset = -(7 + (($number - 59) % 15)); // Past 1-3 weeks
                $hour = $operatingHours[($number * 5) % count($operatingHours)];
            }

            $slotCount = ($number % 4 === 0) ? 2 : 1;
            $hour = min($hour, 22 - $slotCount); // consecutive slots that end by 22:00; never two slots at one time

            $starts = collect(range(0, $slotCount - 1))->map(
                fn (int $offset) => $now->copy()->startOfDay()->addDays($dayOffset)->setTime($hour + $offset, 0)
            );

            $status = match ($number % 12) {
                2 => 'cancelled',
                3 => 'expired',
                4 => 'refund_pending',
                5 => 'refunded',
                7 => 'no_show',
                1 => 'pending_payment',
                default => 'confirmed',
            };

            $prices = $starts->map(function (Carbon $start) use ($court, $courts): int {
                $courtIndex = $courts->search(fn (Court $item): bool => $item->id === $court->id);
                $isWeekend = $start->dayOfWeekIso >= 6;
                $isPeak = $start->hour >= 17 && $start->hour < 22;
                $base = 100000;
                if ($courtIndex >= 10 && $courtIndex < 14) {
                    $base = 350000; // football
                } elseif ($courtIndex >= 15 && $courtIndex < 18) {
                    $base = 250000; // tennis
                } elseif ($courtIndex >= 5 && $courtIndex < 8) {
                    $base = 130000; // pickleball
                }

                return $base + ($isWeekend ? (int) ($base * 0.2) : 0) + ($isPeak ? (int) ($base * 0.25) : 0);
            });

            $total = $prices->sum();
            $createdAt = $starts->first()->copy()->subHours(rand(2, 24));

            $booking = Booking::updateOrCreate(
                ['code' => $code],
                [
                    'user_id' => $customer->id,
                    'court_id' => $court->id,
                    'total_amount' => $total,
                    'status' => $status,
                    'payment_expires_at' => $status === 'pending_payment' ? $now->copy()->addMinutes(15) : ($status === 'expired' ? $now->copy()->subDay() : null),
                    'cancelled_at' => ($status === 'cancelled') ? $now->copy()->subDays(2) : null,
                    'cancellation_reason' => ($status === 'cancelled') ? 'Khách bận lịch đột xuất' : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );

            foreach ($starts as $slotIndex => $start) {
                $slotStatus = match ($status) {
                    'pending_payment' => 'held',
                    'confirmed', 'no_show' => 'confirmed',
                    'expired' => 'expired',
                    default => 'cancelled',
                };

                BookingSlot::updateOrCreate(
                    ['booking_id' => $booking->id, 'starts_at' => $start->format('Y-m-d H:i:s')],
                    [
                        'court_id' => $court->id,
                        'ends_at' => $start->copy()->addHour()->format('Y-m-d H:i:s'),
                        'unit_price' => $prices[$slotIndex],
                        'status' => $slotStatus,
                    ]
                );
            }

            // Every seeded booking gets its reservation mirror, like checkoutV1 does.
            $mapping = $backfill->backfillBooking($booking);
            $createdBookings[] = ['booking' => $booking, 'reservation_id' => $mapping->reservation_id, 'court' => $court, 'user' => $customer, 'status' => $status];

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
                    'payload' => ['demo' => true, 'source' => 'BookingPaymentSeeder'],
                    'received_at' => $paymentStatus === 'manual_review' ? null : $createdAt->copy()->addMinutes(10),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );

            if ($status === 'refund_pending' || $status === 'refunded') {
                Refund::updateOrCreate(
                    ['booking_id' => $booking->id],
                    [
                        'amount' => $total,
                        'status' => $status === 'refunded' ? 'processed' : 'pending',
                        'note' => 'Hoàn tiền vé đặt sân theo chính sách',
                        'processed_by' => ($status === 'refunded' && $admin) ? $admin->id : null,
                        'processed_at' => $status === 'refunded' ? $createdAt->copy()->addHours(2) : null,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]
                );
            }
        }

        // Reviews
        $reviewComments = [
            'Sân thảm mới đánh rất êm chân, độ bám hoàn hảo, hệ thống ánh sáng LED dịu mắt không bị chói khi đập cầu.',
            'Cơ sở vật chất tuyệt vời, điều hòa mát mẻ, nhân viên lễ tân hỗ trợ nhiệt tình và thân thiện!',
            'Sân pickleball đẹp mê ly, mặt sân nảy chuẩn, có quầy nước và chỗ ngồi chờ thoáng mát.',
            'Sân bóng đá cỏ êm, hạt cao su không bị mùi, hệ thống đèn chiếu sáng ban đêm cực kỳ rõ nét.',
            'Hệ thống xem lại pha cầu (VAR) hoạt động rất mượt mà, trải nghiệm đánh giải đỉnh cao.',
            'Không gian rộng rãi, bãi đỗ xe ô tô và xe máy thoải mái, phòng tắm và thay đồ sạch sẽ 10/10.',
            'Đặt sân trên website Aura Court quá nhanh chóng và tiện lợi, quét mã QR vào sân ngay không phải chờ.',
            'Sân tennis mặt cứng độ nảy chuẩn xác, không gian yên tĩnh và đẳng cấp, sẽ quay lại thường xuyên.',
            'Bàn bóng bàn thi đấu Double Fish chuẩn xịn, có rào chắn riêng biệt nên đánh rất tập trung.',
            'Sàn gỗ bóng rổ chuẩn NBA giảm chấn cực tốt, anh em trong đội bóng ai cũng tấm tắc khen ngợi.',
        ];

        $reviewIndex = 0;
        foreach ($createdBookings as $item) {
            if ($item['status'] === 'confirmed' && $reviewIndex < 35) {
                $rating = ($reviewIndex % 6 === 0) ? 4 : 5;
                $comment = $reviewComments[$reviewIndex % count($reviewComments)];

                Review::updateOrCreate(
                    ['reservation_id' => $item['reservation_id']],
                    [
                        'booking_id' => $item['booking']->id,
                        'user_id' => $item['user']->id,
                        'court_id' => $item['court']->id,
                        'rating' => $rating,
                        'body' => $comment,
                        'is_published' => true,
                    ]
                );
                $reviewIndex++;
            }
        }
    }
}
