<?php

namespace App\Domain\Booking\DTOs;

readonly class CreateBookingData
{
    public function __construct(
        public int $userId,
        public int $courtId,
        public array $slots,
        public ?string $couponCode = null,
        public ?int $loyaltyPoints = null,
        public ?int $loyaltyPolicyVersion = null,
        public ?string $idempotencyKey = null,
    ) {}

    public static function fromArray(int $userId, array $data): self
    {
        return new self(
            userId: $userId,
            courtId: (int) $data['court_id'],
            slots: (array) $data['slots'],
            couponCode: $data['coupon_code'] ?? null,
            loyaltyPoints: isset($data['loyalty_points']) && $data['loyalty_points'] !== null ? (int) $data['loyalty_points'] : null,
            loyaltyPolicyVersion: isset($data['loyalty_policy_version']) && $data['loyalty_policy_version'] !== null ? (int) $data['loyalty_policy_version'] : null,
            idempotencyKey: $data['idempotency_key'] ?? null,
        );
    }
}
