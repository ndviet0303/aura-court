<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CommitmentConflictException extends HttpException
{
    /**
     * @param  list<string>  $conflictingBookings
     */
    public function __construct(string $message, public readonly array $conflictingBookings = [])
    {
        parent::__construct(409, $message);
    }

    public function render($request): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'conflicting_bookings' => $this->conflictingBookings,
        ], 409);
    }
}
