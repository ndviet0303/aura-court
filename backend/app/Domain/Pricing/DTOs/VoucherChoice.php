<?php

namespace App\Domain\Pricing\DTOs;

/** The voucher a booking asks for: a public program by id or a private code, never both (design D17). */
readonly class VoucherChoice
{
    private function __construct(public ?int $programId, public ?string $code) {}

    public static function program(int $programId): self
    {
        return new self($programId, null);
    }

    public static function code(string $code): self
    {
        return new self(null, $code);
    }
}
