<?php

namespace App\Domain\Pricing\DTOs;

use App\Models\PriceTable;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * A change of courts' price table links at a switch time, now when left out (design D2): applying a table to the
 * courts, or ending their link of one kind without a replacement.
 */
readonly class PriceTableLinkChange
{
    /** @param  list<int>  $courtIds */
    private function __construct(
        public array $courtIds,
        public string $kind,
        public ?int $priceTableId,
        public ?CarbonImmutable $switchAt,
    ) {}

    /** @param  list<int>  $courtIds */
    public static function apply(PriceTable $table, array $courtIds, ?CarbonInterface $switchAt = null): self
    {
        return new self($courtIds, (string) $table->kind, (int) $table->id, $switchAt ? CarbonImmutable::instance($switchAt) : null);
    }

    /** @param  list<int>  $courtIds */
    public static function end(array $courtIds, string $kind, ?CarbonInterface $endsAt = null): self
    {
        return new self($courtIds, $kind, null, $endsAt ? CarbonImmutable::instance($endsAt) : null);
    }
}
