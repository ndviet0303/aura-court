<?php

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\DTOs\CreateBookingData;
use App\Models\Booking;
use App\Services\Booking\OrdinaryCheckoutService;

class CreateBooking
{
    public function __construct(
        private readonly OrdinaryCheckoutService $checkoutService,
    ) {}

    public function execute(CreateBookingData $data): Booking
    {
        return $this->checkoutService->checkoutV1($data);
    }
}
