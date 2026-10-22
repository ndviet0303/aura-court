<?php

namespace App\Domain\Package\Actions;

use App\Contracts\Repositories\PackagePaymentRequestRepositoryInterface;

/** D7: the scheduled sweep that stores expired on overdue pending requests; reads and the venue-locked paths derive it already. */
class ExpirePackagePaymentRequests
{
    public function __construct(private readonly PackagePaymentRequestRepositoryInterface $requests) {}

    public function execute(): int
    {
        return $this->requests->expireOverdue(now());
    }
}
