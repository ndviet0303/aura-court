<?php

namespace App\Exceptions;

use App\Http\Resources\Package\PackagePaymentRequestResource;
use App\Models\PackagePaymentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** A venue package rule rejected the call (D4, D8); the code lets the owner screen explain it. */
class PackageRuleException extends HttpException
{
    public function __construct(
        int $status,
        public readonly string $errorCode,
        string $message,
        public readonly ?PackagePaymentRequest $pendingRequest = null,
        public readonly ?int $currentAmount = null,
    ) {
        parent::__construct($status, $message);
    }

    public function render(Request $request): JsonResponse
    {
        $body = ['message' => $this->getMessage(), 'code' => $this->errorCode];
        if ($this->pendingRequest !== null) {
            $body['data'] = PackagePaymentRequestResource::make($this->pendingRequest)->resolve($request);
        }
        if ($this->currentAmount !== null) {
            $body['current_amount'] = $this->currentAmount;
        }

        return new JsonResponse($body, $this->getStatusCode());
    }
}
