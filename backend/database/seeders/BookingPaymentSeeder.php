<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\Reservation\LegacyBookingBackfillService;
use App\Services\Reservation\PriceQuoteService;
use Carbon\Carbon;
use Database\Seeders\Concerns\FindsFreeCourtTime;
use Database\Seeders\Concerns\KeepsWakingHours;
use Database\Seeders\Concerns\PaysVenueOwner;
use Illuminate\Database\Seeder;

class BookingPaymentSeeder extends Seeder
{
    use FindsFreeCourtTime;
    use KeepsWakingHours;
    use PaysVenueOwner;

    /**
     * One row per booking DEMO-BK-NNN, in play-time order:
     * [court code, customer (0 = user@example.com, N = khachNN), day offset from today or 'weekend', start hour, hours, kind, review rating, waitlist customers].
     * Kinds: played, no_show, expired (checkout never paid), today (played once it has ended), upcoming.
     * Paid bookings were paid by bank transfer straight to the venue owner; there is no cancellation, refund or reschedule.
     */
    private const SCHEDULE = [
        ['Sân Pickleball 3 - Sunset View Hồ Tây', 1, -22, 17, 2, 'played', 5],
        ['Sân 10 - Yonex Pro Landmark', 8, -20, 18, 2, 'played'],
        ['Sân Bóng Bàn 1 - Double Fish ITTF', 15, -19, 10, 1, 'played'],
        ['Sân Tennis 2 - Hard Court Pro Tây Hồ', 22, -18, 16, 1, 'played', 4],
        ['Sân Bóng Chuyền 1 - Thảm Taraflex Landmark', 5, -17, 18, 2, 'played', 5],
        ['Sân Bóng Đá 5v5 A1 Quần Ngựa', 0, -16, 19, 1, 'played', 4],
        ['Sân Bóng Rổ Trong Nhà NBA Landmark', 12, -15, 19, 2, 'played', 5],
        ['Sân Pickleball 1 - Aura Neon', 19, -14, 8, 1, 'played', 3],
        ['Sân Tennis PMH - Hồ Bán Nguyệt', 2, -13, 7, 2, 'played'],
        ['Sân 9 - Thảm Victor Tây Hồ', 9, -12, 7, 1, 'played', 5],
        ['Sân Bóng Đá 5v5 Sài Gòn Central', 16, -12, 18, 1, 'played'],
        ['Sân Bóng Đá Mini PMH', 23, -11, 18, 1, 'played'],
        ['Sân 6 - Khán Đài Quần Ngựa', 6, -11, 20, 1, 'expired'],
        ['Sân Pickleball PMH 2 - Thi Đấu', 13, -10, 18, 2, 'played'],
        ['Sân Bóng Rổ 1 - Streetball Zone Tây Hồ', 20, -10, 19, 2, 'no_show'],
        ['Sân Tennis 3 - Mặt Cứng US Open Landmark', 3, -9, 17, 1, 'played', 5],
        ['Sân 1 - Thảm BWF Tiêu Chuẩn', 0, -9, 7, 1, 'played'],
        ['Sân 8 - Pro Replay Quần Ngựa', 10, -8, 19, 2, 'played', 4],
        ['Sân Bóng Bàn 2 - Stiga Master Quần Ngựa', 17, -8, 18, 1, 'played'],
        ['Sân Bóng Đá 5v5 Sài Gòn Central', 24, -7, 20, 1, 'played', 5],
        ['Sân Bóng Bàn 2 - Stiga Master Quần Ngựa', 7, -7, 15, 2, 'played', 4],
        ['Sân Bóng Đá 7v7 C1 Quần Ngựa', 14, -6, 19, 2, 'played', 5],
        ['Sân Pickleball 2 - Dynamic Pro', 21, -6, 19, 1, 'no_show'],
        ['Sân Pickleball 1 - Aura Neon', 0, -6, 18, 1, 'played'],
        ['Sân 6 - Khán Đài Quần Ngựa', 4, -5, 17, 1, 'played'],
        ['Sân Pickleball PMH 1 - Mái Che', 11, -5, 16, 1, 'played', 5],
        ['Sân Bóng Đá 7v7 C1 Quần Ngựa', 18, -5, 18, 1, 'expired'],
        ['Sân 5 - VIP Tournament', 1, -4, 20, 2, 'played', 5],
        ['Sân Tennis 1 - Đất Nện French Open Tây Hồ', 8, -4, 6, 2, 'played', 4],
        ['Sân 11 - Sài Gòn Central BWF', 15, -4, 19, 1, 'played'],
        ['Sân Pickleball 4 - Rooftop View Landmark 81', 22, -3, 19, 2, 'played', 5],
        ['Sân Bóng Chuyền 1 - Thảm Taraflex Landmark', 5, -3, 19, 2, 'played', 4],
        ['Sân Tennis 2 - Hard Court Pro Tây Hồ', 0, -3, 18, 2, 'played', 5],
        ['Sân 3 - Tiêu Chuẩn', 12, -2, 6, 2, 'played', 5],
        ['Sân Bóng Đá 5v5 A2 Quần Ngựa', 19, -2, 19, 1, 'played', 3],
        ['Sân Bóng Rổ 1 - Streetball Zone Tây Hồ', 2, -2, 18, 2, 'played', 5],
        ['Sân 7 - Tiêu Chuẩn Quần Ngựa', 9, -2, 20, 1, 'no_show'],
        ['Sân Tennis 3 - Mặt Cứng US Open Landmark', 16, -2, 7, 1, 'expired'],
        ['Sân 2 - Tiêu Chuẩn', 23, -1, 18, 1, 'played', 4],
        ['Sân Pickleball 2 - Dynamic Pro', 6, -1, 7, 1, 'played', 5],
        ['Sân Bóng Bàn 1 - Double Fish ITTF', 13, -1, 20, 1, 'played', 3],
        ['Sân 1 - Thảm BWF Tiêu Chuẩn', 20, 0, 6, 1, 'today'],
        ['Sân Pickleball 1 - Aura Neon', 3, 0, 18, 2, 'today'],
        ['Sân Bóng Đá 5v5 A1 Quần Ngựa', 10, 0, 19, 1, 'today'],
        ['Sân Tennis 2 - Hard Court Pro Tây Hồ', 17, 0, 20, 1, 'today'],
        ['Sân 4 - VIP Đơn/Đôi', 0, 1, 19, 2, 'upcoming'],
        ['Sân Pickleball 3 - Sunset View Hồ Tây', 0, 'weekend', 8, 2, 'upcoming'],
        ['Sân 2 - Tiêu Chuẩn', 24, 1, 6, 2, 'upcoming'],
        ['Sân Pickleball 2 - Dynamic Pro', 7, 1, 18, 2, 'upcoming'],
        ['Sân Tennis 1 - Đất Nện French Open Tây Hồ', 14, 1, 6, 2, 'upcoming'],
        ['Sân 5 - VIP Tournament', 21, 2, 19, 2, 'upcoming'],
        ['Sân Bóng Đá 5v5 A2 Quần Ngựa', 4, 2, 20, 1, 'upcoming'],
        ['Sân Bóng Bàn 1 - Double Fish ITTF', 11, 2, 18, 1, 'upcoming'],
        ['Sân 3 - Tiêu Chuẩn', 18, 2, 7, 1, 'expired'],
        ['Sân 8 - Pro Replay Quần Ngựa', 1, 3, 18, 1, 'upcoming', null, [6]],
        ['Sân Bóng Đá Mini PMH', 8, 3, 19, 1, 'upcoming'],
        ['Sân Bóng Rổ Trong Nhà NBA Landmark', 15, 3, 19, 2, 'upcoming', null, [20]],
        ['Sân Bóng Bàn 2 - Stiga Master Quần Ngựa', 22, 3, 19, 1, 'upcoming'],
        ['Sân Pickleball PMH 1 - Mái Che', 5, 4, 17, 2, 'upcoming'],
        ['Sân Bóng Đá 7v7 C1 Quần Ngựa', 12, 4, 19, 2, 'upcoming', null, [0, 17]],
        ['Sân Bóng Đá 5v5 A1 Quần Ngựa', 19, 4, 18, 1, 'upcoming'],
        ['Sân 11 - Sài Gòn Central BWF', 2, 5, 20, 2, 'upcoming'],
        ['Sân Tennis 3 - Mặt Cứng US Open Landmark', 9, 5, 17, 2, 'upcoming', null, [14]],
        ['Sân Pickleball 4 - Rooftop View Landmark 81', 16, 6, 19, 1, 'upcoming'],
        ['Sân Bóng Chuyền 1 - Thảm Taraflex Landmark', 23, 6, 19, 2, 'upcoming'],
        ['Sân 9 - Thảm Victor Tây Hồ', 6, 6, 19, 1, 'upcoming'],
        ['Sân Pickleball PMH 2 - Thi Đấu', 13, 7, 18, 2, 'upcoming'],
        ['Sân Bóng Đá 5v5 Sài Gòn Central', 20, 8, 18, 1, 'upcoming'],
        ['Sân Bóng Rổ 1 - Streetball Zone Tây Hồ', 3, 8, 17, 1, 'upcoming'],
        ['Sân Tennis PMH - Hồ Bán Nguyệt', 10, 9, 7, 1, 'upcoming'],
        ['Sân Bóng Chuyền 1 - Thảm Taraflex Landmark', 17, 9, 18, 2, 'expired'],
        ['Sân Bóng Rổ 1 - Streetball Zone Tây Hồ', 24, 10, 18, 2, 'upcoming'],
    ];

    private const STATUS = ['played' => 'confirmed', 'upcoming' => 'confirmed', 'no_show' => 'no_show', 'expired' => 'expired'];

    private const SLOT_STATUS = ['confirmed' => 'confirmed', 'no_show' => 'confirmed', 'expired' => 'expired'];

    private const CHECK_IN_STAFF = ['aura-court' => 'staff@demo.auracourt.vn', 'hoang-gia-phu-my-hung' => 'owner.hoanggia@demo.auracourt.vn'];

    private const REVIEWS = [
        'badminton' => [
            'Thảm sân êm, độ bám tốt, đèn không bị chói khi đánh cầu cao. Sẽ quay lại!',
            'Sân rộng, trần cao, cầu bay ổn định. Nhân viên hỗ trợ căng lại lưới rất nhanh.',
            'Giờ tối đông nhưng check-in bằng QR nhanh gọn, sân sạch, nước uống đầy đủ.',
            'Thảm chuẩn BWF, đánh đôi rất đã chân. Giá hợp lý so với chất lượng.',
        ],
        'pickleball' => [
            'Mặt sân acrylic nảy đều, lưới chuẩn thi đấu. Nhóm mình chơi 2 tiếng không chán.',
            'Sân thoáng, có chỗ ngồi chờ và cho thuê vợt, rất tiện cho người mới chơi.',
            'Đèn LED sáng đều cả sân, chơi buổi tối rất thích. Nhân viên thân thiện.',
            'Vạch kẻ rõ ràng, bóng nảy chuẩn. Đặt trên app nhanh, đến nơi là vào chơi luôn.',
        ],
        'football' => [
            'Cỏ nhân tạo mới, ít hạt cao su, đá không bị trơn. Đèn buổi tối sáng rõ.',
            'Sân rộng rãi, khung thành và lưới chắc chắn. Phòng thay đồ và chỗ để xe thoải mái.',
            'Đội mình đá giao hữu rất vui, sân thoát nước tốt dù chiều trước trời mưa.',
            'Giá sân hợp lý, nhân viên chuẩn bị sẵn bóng và áo bib. Sẽ đặt lịch cố định.',
        ],
        'tennis' => [
            'Mặt sân độ nảy chuẩn, không gian yên tĩnh, rất hợp đánh buổi sáng sớm.',
            'Sân sạch, lưới căng chuẩn, có ghế nghỉ và nước mát giữa các set.',
            'Đánh buổi tối đèn đủ sáng, không bị bóng đổ. Nhân viên nhặt bóng nhiệt tình.',
        ],
        'basketball' => [
            'Sàn giảm chấn tốt, rổ chuẩn, đánh 5v5 rất đã. Cả đội đều khen.',
            'Sân thoáng mát, bảng rổ chắc chắn, buổi tối có đèn đầy đủ.',
            'Không gian rộng, có khán đài nhỏ cho bạn bè ngồi cổ vũ.',
        ],
        'table_tennis' => [
            'Bàn thi đấu chuẩn, bóng nảy đều, có vách ngăn riêng nên đánh rất tập trung.',
            'Không gian yên tĩnh, ánh sáng tốt, cho thuê vợt chất lượng. Giá rất mềm.',
            'Sàn chống trơn, bàn mới, hợp cả luyện tập lẫn đánh giao lưu.',
        ],
        'volleyball' => [
            'Sàn Taraflex êm, bật nhảy đập bóng không lo đau gối. Lưới chuẩn thi đấu.',
            'Sân bóng chuyền trong nhà mát mẻ, trần cao, cả đội chơi rất vui.',
        ],
    ];

    private const CRITICAL_REVIEWS = [
        'Mặt sân ổn nhưng giờ cao điểm bãi gửi xe hơi chật, mong sân bố trí thêm người hướng dẫn.',
        'Nhận sân trễ gần 10 phút do ca trước kết thúc muộn, mong quản lý giờ chặt hơn.',
        'Chất lượng sân tốt nhưng phòng thay đồ cần vệ sinh thường xuyên hơn.',
    ];

    public function run(): void
    {
        $now = Carbon::now(config('app.timezone', 'Asia/Ho_Chi_Minh'))->startOfMinute();
        $today = $now->copy()->startOfDay();
        $weekend = $today->copy()->addDays(2);
        if (! $weekend->isWeekend()) {
            $weekend = $weekend->next(Carbon::SATURDAY);
        }

        $emails = array_map(fn (int $number): string => $this->customerEmail($number), range(0, 24));
        $users = User::whereIn('email', [...$emails, 'admin@auracourt.vn', 'manager@demo.auracourt.vn', ...array_values(self::CHECK_IN_STAFF)])
            ->get()->keyBy('email');
        $courts = Court::with(['venue'])->whereIn('code', array_column(self::SCHEDULE, 0))->get()->keyBy('code');
        if ($courts->isEmpty() || ! $users->has('user@example.com')) {
            return;
        }

        $backfill = app(LegacyBookingBackfillService::class);
        $quotes = app(PriceQuoteService::class);
        $reviewCounts = [];

        foreach (self::SCHEDULE as $index => $row) {
            [$courtCode, $customerNo, $day, $hour, $hours, $kind, $rating, $waitlist] = $row + [6 => null, 7 => []];
            $code = sprintf('DEMO-BK-%03d', $index + 1);
            $customer = $users->get($this->customerEmail($customerNo));
            // Never rewrite a seeded booking: a changed booking would no longer match its reservation mapping.
            if (! $customer || ! $courts->has($courtCode) || Booking::where('code', $code)->exists()) {
                continue;
            }

            $date = $day === 'weekend' ? $weekend->copy() : $today->copy()->addDays($day);
            [$court, $startHour] = $this->firstFit($courts[$courtCode], $hour, $hours, fn (Court $candidate, int $at): bool => $this->isFree(
                $candidate->id, $date->copy()->setTime($at, 0), $date->copy()->setTime($at + $hours, 0), $customer->id
            ));
            $start = $date->copy()->setTime($startHour, 0);
            $end = $start->copy()->addHours($hours);
            $venue = $court->venue;
            $organizationId = (int) $venue->organization_id;
            $slotStarts = array_map(fn (int $offset): Carbon => $start->copy()->addHours($offset), range(0, $hours - 1));
            $snapshots = $quotes->priceSnapshots($court, $slotStarts, 60);
            $total = (int) array_sum(array_column($snapshots, 'unit_price'));

            if ($kind === 'today') {
                $kind = $end->lte($now) ? 'played' : 'upcoming';
            }

            // Booked a day or more ahead and never "just now": later seeders date match posts and feed items from it.
            $createdAt = $start->copy()->subHours(26 + ($index * 11) % 70)->min($now->copy()->subDay()->subMinutes(($index * 47) % 1440));
            // Only ever earlier, so the booking stays a day ahead, and far enough that its 15-minute checkout ends before midnight.
            $checkoutEnd = $createdAt->copy()->addMinutes(15);
            $createdAt = $this->awake($checkoutEnd, before: $checkoutEnd)->subMinutes(15);
            $paidAt = $kind === 'expired' ? null : $createdAt->copy()->addMinutes(3 + $index % 9);
            $this->assertTimeline($code, $now, $start, $paidAt, [$createdAt, $paidAt]);

            $status = self::STATUS[$kind];
            $booking = Booking::forceCreate([
                'code' => $code,
                'user_id' => $customer->id,
                'court_id' => $court->id,
                'total_amount' => $total,
                'status' => $status,
                'payment_model' => 'direct_owner_v1',
                'payment_expires_at' => $kind === 'expired' ? $createdAt->copy()->addMinutes(15) : null,
                'price_snapshot' => $snapshots,
                'metadata' => ['venue_id' => $venue->id, 'organization_id' => $organizationId],
                'created_at' => $createdAt,
                'updated_at' => $paidAt ?? $createdAt,
            ]);

            foreach ($slotStarts as $position => $slotStart) {
                BookingSlot::forceCreate([
                    'booking_id' => $booking->id,
                    'court_id' => $court->id,
                    'starts_at' => $slotStart,
                    'ends_at' => $slotStart->copy()->addHour(),
                    'unit_price' => $snapshots[$position]['unit_price'],
                    'status' => self::SLOT_STATUS[$status],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }

            // Every seeded booking gets its reservation mirror through the legacy backfill.
            $mapping = $backfill->backfillBooking($booking);
            $reservation = Reservation::withoutGlobalScope('tenant_isolation')->findOrFail($mapping->reservation_id);
            $reservation->forceFill(array_filter([
                'confirmed_at' => $status === 'confirmed' ? $paidAt : null,
                'metadata' => $kind === 'played' ? [...(array) $reservation->metadata, 'operational_status' => 'completed'] : null,
            ]))->saveQuietly();

            if ($paidAt) {
                $this->payOwner($venue, $customer, 'booking', $reservation->code, $total, $createdAt, $paidAt);
            }

            if ($kind === 'played') {
                $staff = $users->get(self::CHECK_IN_STAFF[$venue->slug] ?? 'manager@demo.auracourt.vn');
                $checkedInAt = $start->copy()->subMinutes(5 + $index % 10);
                $checkedOutAt = $end->copy()->addMinutes($index % 8)->min($now->copy());
                if ($staff) {
                    BookingAttendance::forceCreate([
                        'organization_id' => $organizationId,
                        'reservation_id' => $reservation->id,
                        'legacy_booking_id' => $booking->id,
                        'status' => 'checked_out',
                        'checked_in_at' => $checkedInAt,
                        'checked_in_by' => $staff->id,
                        'checked_out_at' => $checkedOutAt,
                        'checked_out_by' => $staff->id,
                        'metadata' => ['staff_name' => $staff->name, 'scanned_input' => $code, 'operational_status' => 'completed', 'unpaid_receivable' => 0],
                        'created_at' => $checkedInAt,
                        'updated_at' => $checkedOutAt,
                    ]);
                }

                if ($rating) {
                    $sport = str_starts_with($court->sport_type, 'football') ? 'football' : $court->sport_type;
                    $pool = $rating === 3 ? self::CRITICAL_REVIEWS : self::REVIEWS[$sport];
                    $key = $rating === 3 ? 'critical' : $sport;
                    $reviewCounts[$key] = ($reviewCounts[$key] ?? -1) + 1;
                    $reviewedAt = $this->awake($end->copy()->addHours(2 + $index % 30)->min($now->copy()->subMinute()), $end);
                    Review::forceCreate([
                        'reservation_id' => $reservation->id,
                        'booking_id' => $booking->id,
                        'user_id' => $customer->id,
                        'court_id' => $court->id,
                        'rating' => $rating,
                        'body' => $pool[$reviewCounts[$key] % count($pool)],
                        'is_published' => true,
                        'created_at' => $reviewedAt,
                        'updated_at' => $reviewedAt,
                    ]);
                }
            }

            foreach ($waitlist as $position => $waitingNo) {
                $waiting = $users->get($this->customerEmail($waitingNo));
                $joinedAt = $this->awake($createdAt->copy()->addHours(3 + $position)->min($now->copy()->subMinutes(10)), $createdAt);
                if ($waiting && $waiting->id !== $customer->id && $status === 'confirmed') {
                    WaitlistEntry::forceCreate([
                        'user_id' => $waiting->id,
                        'court_id' => $court->id,
                        'starts_at' => $start,
                        'ends_at' => $start->copy()->addHour(),
                        'status' => 'waiting',
                        'created_at' => $joinedAt,
                        'updated_at' => $joinedAt,
                    ]);
                }
            }
        }
    }

    private function customerEmail(int $number): string
    {
        return $number === 0 ? 'user@example.com' : sprintf('khach%02d@demo.auracourt.vn', $number);
    }

    /** @param  array<int, Carbon|null>  $moments  created, paid */
    private function assertTimeline(string $code, Carbon $now, Carbon $start, ?Carbon $paidAt, array $moments): void
    {
        $moments = array_values(array_filter($moments));
        foreach ($moments as $position => $moment) {
            if ($moment->gt($now) || ($position > 0 && $moment->lt($moments[$position - 1]))) {
                throw new \RuntimeException("Seeded timeline for {$code} is out of order.");
            }
        }
        if ($paidAt?->gte($start)) {
            throw new \RuntimeException("Seeded payment for {$code} is not before play time.");
        }
    }
}
