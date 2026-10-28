<?php

namespace App\Domain\BookingTransfer\Rules;

use App\Contracts\Repositories\BookingTransferRepositoryInterface;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Infrastructure\FeatureFlagService;
use App\Services\Social\ResalePriceCap;
use Carbon\CarbonInterface;

/**
 * Module 08.2 D8: the one place that says whether a booking may be listed, put in a transaction or confirmed
 * (BR-176..BR-178, BR-181, BR-184). Each refusal is {code, message}; errors carry the code the frontend maps.
 */
final class TransferEligibility
{
    public const CUTOFF_HOURS = 2;

    public const FEATURE = 'marketplace_slot_resale';

    public function __construct(
        private readonly BookingTransferRepositoryInterface $transfers,
        private readonly ResalePriceCap $priceCap,
        private readonly FeatureFlagService $features,
    ) {}

    /** BR-177: still open while strictly more than two hours remain; at exactly two hours it is closed. */
    public static function beforeCutoff(CarbonInterface $startsAt): bool
    {
        return $startsAt->copy()->subHours(self::CUTOFF_HOURS)->isFuture();
    }

    /**
     * Why $holder may not transfer the booking now, or null. $price is checked against the cap when given;
     * $heldExcept checks for another pending transaction (null: not checked, 0: any); $listed refuses a booking
     * already on the market.
     *
     * @return array{code: string, message: string}|null
     */
    public function refusal(?Reservation $reservation, User $holder, ?int $price = null, ?int $heldExcept = null, bool $listed = false): ?array
    {
        // A booking that does not exist is refused like someone else's, so ids reveal nothing.
        if ($reservation === null || (int) $reservation->user_id !== (int) $holder->id) {
            return self::reason('TRANSFER_NOT_HOLDER', 'Bạn không còn quyền sử dụng booking này.');
        }
        if (! $this->features->enabled(self::FEATURE, (int) $reservation->organization_id)) {
            return self::reason('TRANSFER_FEATURE_OFF', 'Cơ sở của booking này chưa mở chuyển nhượng.');
        }
        // A booking is confirmed only once paid, or at once when nothing is payable.
        if ($reservation->status !== 'confirmed') {
            return self::reason('TRANSFER_NOT_CONFIRMED', 'Chỉ chuyển nhượng được booking đã thanh toán và đã xác nhận.');
        }
        $facts = $this->transfers->facts($reservation, $heldExcept ?: null);
        if ($facts['used']) {
            return self::reason('TRANSFER_USED', 'Booking đã được sử dụng.');
        }
        if ($facts['transferred']) {
            return self::reason('TRANSFER_ALREADY_TRANSFERRED', 'Booking đã được chuyển nhượng một lần.');
        }
        if ($facts['incident'] || $reservation->isDisputed()) {
            return self::reason('TRANSFER_INCIDENT_OPEN', 'Booking đang có sự cố chưa giải quyết.');
        }
        $startsAt = $reservation->starts_at;
        if ($startsAt === null || ! self::beforeCutoff($startsAt)) {
            return self::reason('TRANSFER_CUTOFF', 'Đã đến mốc ngừng chuyển nhượng (2 giờ trước giờ bắt đầu).');
        }
        if ($heldExcept !== null && $facts['held']) {
            return self::reason('TRANSFER_HELD', 'Lượt đang được giữ trong một giao dịch khác.');
        }
        if ($listed && $facts['listed']) {
            return self::reason('TRANSFER_LISTED', 'Booking đã có trong một tin chuyển nhượng khác.');
        }
        $cap = $this->maxPrice($reservation);
        if ($price !== null && $price > $cap) {
            return self::reason('TRANSFER_PRICE_CAP', 'Giá chuyển nhượng vượt số tiền đã thanh toán ('.number_format($cap, 0, ',', '.').'₫).');
        }

        return null;
    }

    /** BR-181: what the booking paid after its voucher. */
    public function maxPrice(Reservation $reservation): int
    {
        return $this->priceCap->forReservation($reservation);
    }

    /** D8: the actor (seller) is not locked, has a verified email and a complete profile. */
    public function assertActor(User $user): void
    {
        abort_if($user->isRestricted(), 403, 'Tài khoản của bạn đã bị khóa hoặc hạn chế quyền truy cập.');
        if (! $user->hasVerifiedEmail()) {
            self::fail('EMAIL_UNVERIFIED', 'Vui lòng xác thực email trước khi chuyển nhượng booking.', 403);
        }
        if (! $user->hasCompleteProfile()) {
            self::fail('PROFILE_INCOMPLETE', 'Vui lòng cập nhật đầy đủ họ tên và số điện thoại trước khi chuyển nhượng booking.');
        }
    }

    /** D8: the buyer becomes the holder the venue contacts (BR-188, BR-192), so they meet the same conditions. */
    public function assertBuyer(User $seller, User $buyer): void
    {
        if ((int) $seller->id === (int) $buyer->id) {
            self::fail('TRANSFER_SELF', 'Không thể chuyển nhượng booking cho chính mình.');
        }
        if ($buyer->isRestricted() || ! $buyer->hasVerifiedEmail() || ! $buyer->hasCompleteProfile()) {
            self::fail('TRANSFER_BUYER_INELIGIBLE', 'Người nhận chưa thể nhận chuyển nhượng: tài khoản bị khóa, chưa xác thực email hoặc chưa đủ họ tên và số điện thoại.');
        }
    }

    /**
     * 422 for the whole request when any booking is refused, naming each refused booking; nothing is written.
     *
     * @param  array<int, array{code: string, message: string}|null>  $refusals  keyed by reservation id
     */
    public static function failIfRefused(array $refusals): void
    {
        $items = [];
        foreach (array_filter($refusals) as $reservationId => $refusal) {
            $items[] = ['reservation_id' => (int) $reservationId, ...$refusal];
        }
        if ($items !== []) {
            self::fail($items[0]['code'], $items[0]['message'], 422, $items);
        }
    }

    /** @param  list<array{reservation_id: int, code: string, message: string}>  $items */
    public static function fail(string $code, string $message, int $status = 422, array $items = []): never
    {
        abort(response()->json(['message' => $message, 'code' => $code] + ($items === [] ? [] : ['items' => $items]), $status));
    }

    /** @return array{code: string, message: string} */
    private static function reason(string $code, string $message): array
    {
        return ['code' => $code, 'message' => $message];
    }
}
