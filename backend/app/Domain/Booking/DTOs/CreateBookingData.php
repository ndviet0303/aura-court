<?php

namespace App\Domain\Booking\DTOs;

readonly class CreateBookingData
{
    public function __construct(
        public int $userId,
        public int $courtId,
        public array $slots,
        public ?string $couponCode = null,
    ) {}

    public static function fromArray(int $userId, array $data): self
    {
        return new self(
            userId: $userId,
            courtId: (int) $data['court_id'],
            slots: (array) $data['slots'],
            couponCode: $data['coupon_code'] ?? null,
        );
    }
}
