<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** An open or resume of a venue or court whose conditions do not hold: 422 listing every missing item (D1). */
class OpeningConditionsUnmetException extends HttpException
{
    /**
     * @param  list<array<string, mixed>>  $missingItems  each {code, message, ...extra}
     */
    public function __construct(string $message, public readonly array $missingItems)
    {
        parent::__construct(422, $message);
    }

    public function render($request): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage(), 'missing_items' => $this->missingItems], 422);
    }
}
