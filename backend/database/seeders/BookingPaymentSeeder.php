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
     * [court slug, customer (0 = user@example.com, N = khachNN), day offset from today or 'weekend', start hour, hours, kind, review rating, waitlist customers].
     * Kinds: played, no_show, expired (checkout never paid), today (played once it has ended), upcoming.
     * Paid bookings were paid by bank transfer straight to the venue owner; there is no cancellation, refund or reschedule.
     */
    private const SCHEDULE = [
        ['san-pickleball-3-sunset-tay-ho', 1, -22, 17, 2, 'played', 5],
        ['san-10-yonex-pro-landmark', 8, -20, 18, 2, 'played'],
        ['san-bong-ban-1-double-fish-cau-giay', 15, -19, 10, 1, 'played'],
        ['san-tennis-2-hard-court-tay-ho', 22, -18, 16, 1, 'played', 4],
        ['san-bong-chuyen-1-taraflex-landmark', 5, -17, 18, 2, 'played', 5],
        ['san-bong-da-5v5-a1-quan-ngua', 0, -16, 19, 1, 'played', 4],
        ['san-bong-ro-in-house-nba-landmark', 12, -15, 19, 2, 'played', 5],
        ['san-pickleball-1-neon-cau-giay', 19, -14, 8, 1, 'played', 3],
        ['san-tennis-pmh-ho-ban-nguyet', 2, -13, 7, 2, 'played'],
        ['san-9-tham-victor-tay-ho', 9, -12, 7, 1, 'played', 5],
        ['san-bong-da-5v5-sai-gon-central', 16, -12, 18, 1, 'played'],
        ['san-bong-da-mini-pmh', 23, -11, 18, 1, 'played'],
        ['san-6-khan-dai-quan-ngua', 6, -11, 20, 1, 'expired'],
        ['san-pickleball-pmh-2', 13, -10, 18, 2, 'played'],
        ['san-bong-ro-1-streetball-tay-ho', 20, -10, 19, 2, 'no_show'],
        ['san-tennis-3-mat-cung-landmark', 3, -9, 17, 1, 'played', 5],
        ['san-1-tham-bwf-cau-giay', 0, -9, 7, 1, 'played'],
        ['san-8-pro-replay-quan-ngua', 10, -8, 19, 2, 'played', 4],
        ['san-bong-ban-2-stiga-master-quan-ngua', 17, -8, 18, 1, 'played'],
        ['san-bong-da-5v5-sai-gon-central', 24, -7, 20, 1, 'played', 5],
        ['san-bong-ban-2-stiga-master-quan-ngua', 7, -7, 15, 2, 'played', 4],
        ['san-bong-da-7v7-c1-quan-ngua', 14, -6, 19, 2, 'played', 5],
        ['san-pickleball-2-dynamic-cau-giay', 21, -6, 19, 1, 'no_show'],
        ['san-pickleball-1-neon-cau-giay', 0, -6, 18, 1, 'played'],
        ['san-6-khan-dai-quan-ngua', 4, -5, 17, 1, 'played'],
        ['san-pickleball-pmh-1', 11, -5, 16, 1, 'played', 5],
        ['san-bong-da-7v7-c1-quan-ngua', 18, -5, 18, 1, 'expired'],
        ['san-5-vip-tournament-cau-giay', 1, -4, 20, 2, 'played', 5],
        ['san-tennis-1-dat-nen-tay-ho', 8, -4, 6, 2, 'played', 4],
        ['san-11-sai-gon-central-bwf', 15, -4, 19, 1, 'played'],
        ['san-pickleball-4-rooftop-saigon', 22, -3, 19, 2, 'played', 5],
        ['san-bong-chuyen-1-taraflex-landmark', 5, -3, 19, 2, 'played', 4],
        ['san-tennis-2-hard-court-tay-ho', 0, -3, 18, 2, 'played', 5],
        ['san-3-tieu-chuan-cau-giay', 12, -2, 6, 2, 'played', 5],
        ['san-bong-da-5v5-a2-quan-ngua', 19, -2, 19, 1, 'played', 3],
        ['san-bong-ro-1-streetball-tay-ho', 2, -2, 18, 2, 'played', 5],
        ['san-7-tieu-chuan-quan-ngua', 9, -2, 20, 1, 'no_show'],
        ['san-tennis-3-mat-cung-landmark', 16, -2, 7, 1, 'expired'],
        ['san-2-tieu-chuan-cau-giay', 23, -1, 18, 1, 'played', 4],
        ['san-pickleball-2-dynamic-cau-giay', 6, -1, 7, 1, 'played', 5],
        ['san-bong-ban-1-double-fish-cau-giay', 13, -1, 20, 1, 'played', 3],
        ['san-1-tham-bwf-cau-giay', 20, 0, 6, 1, 'today'],
        ['san-pickleball-1-neon-cau-giay', 3, 0, 18, 2, 'today'],
        ['san-bong-da-5v5-a1-quan-ngua', 10, 0, 19, 1, 'today'],
        ['san-tennis-2-hard-court-tay-ho', 17, 0, 20, 1, 'today'],
        ['san-4-vip-cau-giay', 0, 1, 19, 2, 'upcoming'],
        ['san-pickleball-3-sunset-tay-ho', 0, 'weekend', 8, 2, 'upcoming'],
        ['san-2-tieu-chuan-cau-giay', 24, 1, 6, 2, 'upcoming'],
        ['san-pickleball-2-dynamic-cau-giay', 7, 1, 18, 2, 'upcoming'],
        ['san-tennis-1-dat-nen-tay-ho', 14, 1, 6, 2, 'upcoming'],
        ['san-5-vip-tournament-cau-giay', 21, 2, 19, 2, 'upcoming'],
        ['san-bong-da-5v5-a2-quan-ngua', 4, 2, 20, 1, 'upcoming'],
        ['san-bong-ban-1-double-fish-cau-giay', 11, 2, 18, 1, 'upcoming'],
        ['san-3-tieu-chuan-cau-giay', 18, 2, 7, 1, 'expired'],
        ['san-8-pro-replay-quan-ngua', 1, 3, 18, 1, 'upcoming', null, [6]],
        ['san-bong-da-mini-pmh', 8, 3, 19, 1, 'upcoming'],
        ['san-bong-ro-in-house-nba-landmark', 15, 3, 19, 2, 'upcoming', null, [20]],
        ['san-bong-ban-2-stiga-master-quan-ngua', 22, 3, 19, 1, 'upcoming'],
        ['san-pickleball-pmh-1', 5, 4, 17, 2, 'upcoming'],
        ['san-bong-da-7v7-c1-quan-ngua', 12, 4, 19, 2, 'upcoming', null, [0, 17]],
        ['san-bong-da-5v5-a1-quan-ngua', 19, 4, 18, 1, 'upcoming'],
        ['san-11-sai-gon-central-bwf', 2, 5, 20, 2, 'upcoming'],
        ['san-tennis-3-mat-cung-landmark', 9, 5, 17, 2, 'upcoming', null, [14]],
        ['san-pickleball-4-rooftop-saigon', 16, 6, 19, 1, 'upcoming'],
        ['san-bong-chuyen-1-taraflex-landmark', 23, 6, 19, 2, 'upcoming'],
        ['san-9-tham-victor-tay-ho', 6, 6, 19, 1, 'upcoming'],
        ['san-pickleball-pmh-2', 13, 7, 18, 2, 'upcoming'],
        ['san-bong-da-5v5-sai-gon-central', 20, 8, 18, 1, 'upcoming'],
        ['san-bong-ro-1-streetball-tay-ho', 3, 8, 17, 1, 'upcoming'],
        ['san-tennis-pmh-ho-ban-nguyet', 10, 9, 7, 1, 'upcoming'],
        ['san-bong-chuyen-1-taraflex-landmark', 17, 9, 18, 2, 'expired'],
        ['san-bong-ro-1-streetball-tay-ho', 24, 10, 18, 2, 'upcoming'],
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
        $courts = Court::with(['venue'])->whereIn('slug', array_column(self::SCHEDULE, 0))->get()->keyBy('slug');
        if ($courts->isEmpty() || ! $users->has('user@example.com')) {
            return;
        }

        $backfill = app(LegacyBookingBackfillService::class);
        $quotes = app(PriceQuoteService::class);
        $reviewCounts = [];

        foreach (self::SCHEDULE as $index => $row) {
            [$slug, $customerNo, $day, $hour, $hours, $kind, $rating, $waitlist] = $row + [6 => null, 7 => []];
            $code = sprintf('DEMO-BK-%03d', $index + 1);
            $customer = $users->get($this->customerEmail($customerNo));
            // Never rewrite a seeded booking: a changed booking would no longer match its reservation mapping.
            if (! $customer || ! $courts->has($slug) || Booking::where('code', $code)->exists()) {
                continue;
            }

            $date = $day === 'weekend' ? $weekend->copy() : $today->copy()->addDays($day);
            [$court, $startHour] = $this->firstFit($courts[$slug], $hour, $hours, fn (Court $candidate, int $at): bool => $this->isFree(
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
