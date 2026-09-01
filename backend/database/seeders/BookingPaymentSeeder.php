<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Review;
use App\Models\User;
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

        $createdBookings = [];

        foreach (range(1, 64) as $number) {
            $court = $courts[($number - 1) % $courts->count()];
            $customer = $customers[($number - 1) % $customers->count()];
            $dayOffset = 1 + intdiv($number - 1, $courts->count());
            $hour = 6 + ((($number - 1) % 8) * 2);
            $slotCount = ($number % 3 === 0) ? 2 : 1;

            $starts = collect(range(0, $slotCount - 1))->map(
                fn (int $offset) => $now->copy()->startOfDay()->addDays($dayOffset)->setTime($hour + $offset, 0)
            );

            $status = match ($number % 10) {
                2 => 'cancelled',
                3 => 'expired',
                4 => 'refund_pending',
                5 => 'refunded',
                7 => 'no_show',
                default => $number % 10 === 1 ? 'pending_payment' : 'confirmed',
            };

            $isHistorical = in_array($status, ['cancelled', 'expired', 'refund_pending', 'refunded', 'no_show'], true) || ($number <= 20);
            if ($isHistorical) {
                $starts = $starts->map(fn (Carbon $start) => $start->subDays(15 + ($number % 20)));
            }

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
            $code = sprintf('DEMO-BK-%03d', $number);

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
                ]
            );

            $createdBookings[] = ['booking' => $booking, 'court' => $court, 'user' => $customer, 'status' => $status];

            foreach ($starts as $slotIndex => $start) {
                $slotStatus = match ($status) {
                    'pending_payment' => 'held',
                    'confirmed', 'no_show' => 'confirmed',
                    'expired' => 'expired',
                    default => 'cancelled',
                };

                BookingSlot::updateOrCreate(
                    ['booking_id' => $booking->id, 'starts_at' => $start->copy()->utc()->format('Y-m-d H:i:s')],
                    [
                        'court_id' => $court->id,
                        'ends_at' => $start->copy()->addHour()->utc()->format('Y-m-d H:i:s'),
                        'unit_price' => $prices[$slotIndex],
                        'status' => $slotStatus,
                    ]
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
                    'payload' => ['demo' => true, 'source' => 'BookingPaymentSeeder'],
                    'received_at' => $paymentStatus === 'manual_review' ? null : $now->copy()->subDays(1),
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
                        'processed_at' => $status === 'refunded' ? $now->copy()->subDays(1) : null,
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
                    ['booking_id' => $item['booking']->id],
                    [
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
