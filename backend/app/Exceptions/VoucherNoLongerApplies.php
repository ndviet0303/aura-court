<?php

namespace App\Exceptions;

use App\Domain\Pricing\DTOs\RentalQuote;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A voucher that does not apply when listed, entered or submitted (design D20), or no longer applies at
 * confirmation (D11). $reason is a D20 reason, or discount_changed when consume cannot confirm the discount recorded
 * on the booking. $withoutVoucher holds the amounts without the voucher; every throw in module 3 passes them (the
 * rental recorded on the booking when its time can no longer be priced), so rental and total are integers.
 */
class VoucherNoLongerApplies extends RuntimeException
{
    private const MESSAGES = [
        'not_found' => 'Không tìm thấy voucher này.',
        'not_started' => 'Voucher chưa tới thời gian áp dụng.',
        'expired' => 'Voucher đã hết hạn.',
        'used' => 'Mã voucher đã được sử dụng.',
        'held_elsewhere' => 'Mã voucher đang được giữ cho một đơn khác.',
        'stopped' => 'Chương trình khuyến mãi đã dừng.',
        'exhausted' => 'Voucher đã hết lượt sử dụng.',
        'court_not_covered' => 'Voucher không áp dụng cho sân này.',
        'minimum_not_met' => 'Tiền thuê sân chưa đạt mức tối thiểu để dùng voucher.',
        'discount_changed' => 'Voucher không còn áp dụng như lúc đặt đơn.',
    ];

    public function __construct(public readonly string $reason, public readonly ?RentalQuote $withoutVoucher = null)
    {
        parent::__construct(self::MESSAGES[$reason] ?? 'Voucher không áp dụng được cho đơn này.');
    }

    /** A refused voucher is the customer's input, not a fault, so it is not logged. */
    public function report(): void {}

    /** The one 422 VOUCHER_INVALID body (D20): the reason and the amounts without the voucher. */
    public function render(mixed $request = null): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'code' => 'VOUCHER_INVALID',
            'reason' => $this->reason,
            'rental' => $this->withoutVoucher?->rental,
            'discount' => 0,
            'total' => $this->withoutVoucher?->total,
        ], 422);
    }
}
