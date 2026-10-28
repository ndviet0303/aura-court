<?php

namespace App\Domain\Booking\DTOs;

use App\Exceptions\VoucherNoLongerApplies;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Why the ordinary booking flow refused one occurrence (BR-169): the body it would have answered for that booking
 * alone (message, and code with amounts for PRICE_CHANGED, PAYMENT_WINDOW_CLOSED or VOUCHER_INVALID). Any other
 * failure is not a refusal and is rethrown.
 */
readonly class OccurrenceRefusal
{
    /** @param  array<string, mixed>  $body */
    private function __construct(public array $body) {}

    /** UC-8.6 E-3: the single-use code is also named on another occurrence of the list. */
    public static function codeShared(): self
    {
        return new self(['message' => 'Mã voucher chỉ dùng được cho một lượt. Hãy giữ mã ở một lượt và bỏ ở các lượt còn lại.', 'code' => 'VOUCHER_CODE_SHARED']);
    }

    public static function from(Throwable $e): self
    {
        return new self(match (true) {
            $e instanceof VoucherNoLongerApplies => $e->render()->getData(true),
            $e instanceof HttpResponseException => (array) json_decode((string) $e->getResponse()->getContent(), true),
            $e instanceof ValidationException => ['message' => collect($e->errors())->flatten()->first() ?? $e->getMessage()],
            $e instanceof HttpExceptionInterface => ['message' => $e->getMessage()],
            default => throw $e,
        });
    }
}
