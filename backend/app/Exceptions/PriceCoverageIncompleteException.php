<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** A price publication or link change would leave bookable court time without a price (design D3). */
class PriceCoverageIncompleteException extends HttpException
{
    /**
     * @param  list<array{court_id: int, court_name: string, weekday: ?int, date: ?string, start: string, end: string}>  $missingItems
     */
    public function __construct(public readonly array $missingItems)
    {
        parent::__construct(422, 'Bảng giá chưa phủ kín giờ mở của sân. Vui lòng bổ sung giá cho các khung giờ còn thiếu.');
    }

    public function render($request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'code' => 'PRICE_COVERAGE_INCOMPLETE',
            'missing_items' => $this->missingItems,
        ], 422);
    }
}
