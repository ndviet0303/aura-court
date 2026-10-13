<?php

namespace Tests\Unit;

use App\Services\Tournament\TournamentService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TournamentEntryFeeTest extends TestCase
{
    public static function fees(): array
    {
        return [
            'dot thousands separator' => ['300.000', 300000],
            'admin label' => ['300.000 ₫ / VĐV', 300000],
            'plain digits' => ['300000', 300000],
            'int' => [300000, 300000],
            'empty string' => ['', 0],
            'null' => [null, 0],
            'no digits' => ['Miễn phí', 0],
        ];
    }

    #[DataProvider('fees')]
    public function test_parse_entry_fee(mixed $fee, int $expected): void
    {
        $this->assertSame($expected, TournamentService::parseEntryFee($fee));
    }
}
