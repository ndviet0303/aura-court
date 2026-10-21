<?php

namespace Tests\Unit;

use App\Services\Social\MatchService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MatchBillCalculationTest extends TestCase
{
    private MatchService $matchService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matchService = app(MatchService::class);
    }

    public function test_standard_calculation_scenario(): void
    {
        // 2 giờ (120 phút) × 150,000 + 8 quả × 25,000 = 500,000; 8 người = 62,500/người
        $result = $this->matchService->calculateBill(
            headcount: 8,
            durationMinutes: 120,
            courtHourlyRate: 150000,
            shuttleCount: 8,
            shuttleUnitPrice: 25000
        );

        $this->assertSame(300000, $result['court_subtotal']);
        $this->assertSame(200000, $result['shuttlecock_subtotal']);
        $this->assertSame(500000, $result['total_amount']);
        $this->assertSame(62500, $result['per_person_amount']);
        $this->assertFalse($result['is_approximate']);
        $this->assertSame(8, $result['divisor']);
    }

    public function test_fractional_hours_duration(): void
    {
        // 1.5 giờ (90 phút) × 200,000 = 300,000; 6 quả × 25,000 = 150,000; tổng 450,000
        $result = $this->matchService->calculateBill(
            headcount: 6,
            durationMinutes: 90,
            courtHourlyRate: 200000,
            shuttleCount: 6,
            shuttleUnitPrice: 25000
        );

        $this->assertSame(300000, $result['court_subtotal']);
        $this->assertSame(150000, $result['shuttlecock_subtotal']);
        $this->assertSame(450000, $result['total_amount']);
        $this->assertSame(75000, $result['per_person_amount']);
        $this->assertFalse($result['is_approximate']);
    }

    public function test_zero_shuttlecocks_is_allowed(): void
    {
        $result = $this->matchService->calculateBill(
            headcount: 4,
            durationMinutes: 60,
            courtHourlyRate: 120000,
            shuttleCount: 0,
            shuttleUnitPrice: 25000
        );

        $this->assertSame(120000, $result['court_subtotal']);
        $this->assertSame(0, $result['shuttlecock_subtotal']);
        $this->assertSame(120000, $result['total_amount']);
        $this->assertSame(30000, $result['per_person_amount']);
        $this->assertFalse($result['is_approximate']);
    }

    public function test_non_integral_share_marks_approximate(): void
    {
        // 500,000 chia cho 6 người = 83,333.33 => 83,333 VND, is_approximate = true
        $result = $this->matchService->calculateBill(
            headcount: 6,
            durationMinutes: 120,
            courtHourlyRate: 150000,
            shuttleCount: 8,
            shuttleUnitPrice: 25000
        );

        $this->assertSame(500000, $result['total_amount']);
        $this->assertSame(83333, $result['per_person_amount']);
        $this->assertTrue($result['is_approximate']);
        $this->assertSame(6, $result['divisor']);
    }

    public function test_court_subtotal_drops_the_fraction_of_a_vnd(): void
    {
        // 1 phút @ 100,000 VND/h = 1666.6666... -> bỏ phần thập phân thành 1666 (BR-67)
        $result = $this->matchService->calculateBill(
            headcount: 1,
            durationMinutes: 1,
            courtHourlyRate: 100000,
            shuttleCount: 0,
            shuttleUnitPrice: 0
        );

        $this->assertSame(1666, $result['court_subtotal']);

        // 3 phút @ 100,000 VND/h = 5000 VND
        $result2 = $this->matchService->calculateBill(
            headcount: 1,
            durationMinutes: 3,
            courtHourlyRate: 100000,
            shuttleCount: 0,
            shuttleUnitPrice: 0
        );

        $this->assertSame(5000, $result2['court_subtotal']);
    }

    public function test_zero_headcount_is_rejected(): void
    {
        $this->expectException(HttpException::class);
        $this->matchService->calculateBill(
            headcount: 0,
            durationMinutes: 60,
            courtHourlyRate: 100000,
            shuttleCount: 1,
            shuttleUnitPrice: 25000
        );
    }

    public function test_nonpositive_duration_is_rejected(): void
    {
        $this->expectException(HttpException::class);
        $this->matchService->calculateBill(
            headcount: 4,
            durationMinutes: 0,
            courtHourlyRate: 100000,
            shuttleCount: 1,
            shuttleUnitPrice: 25000
        );
    }

    public function test_negative_prices_are_rejected(): void
    {
        $this->expectException(HttpException::class);
        $this->matchService->calculateBill(
            headcount: 4,
            durationMinutes: 60,
            courtHourlyRate: -1000,
            shuttleCount: 1,
            shuttleUnitPrice: 25000
        );
    }

    public function test_negative_shuttlecock_count_is_rejected(): void
    {
        $this->expectException(HttpException::class);
        $this->matchService->calculateBill(
            headcount: 4,
            durationMinutes: 60,
            courtHourlyRate: 100000,
            shuttleCount: -2,
            shuttleUnitPrice: 25000
        );
    }
}
