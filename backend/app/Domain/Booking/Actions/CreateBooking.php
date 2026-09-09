<?php

namespace App\Domain\Booking\Actions;

use App\Contracts\Repositories\BookingRepositoryInterface;
use App\Contracts\Repositories\CourtBlockRepositoryInterface;
use App\Contracts\Repositories\CourtPriceSlotRepositoryInterface;
use App\Contracts\Repositories\CourtRepositoryInterface;
use App\Domain\Booking\DTOs\CreateBookingData;
use App\Models\Booking;
use App\Notifications\AuraBookingNotification;
use App\Services\Booking\DiscountService;
use App\Services\Booking\WaitlistService;
use App\Services\Finance\FinancialPostingService;
use App\Services\Payment\WalletPaymentService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateBooking
{
    public function __construct(
        private readonly \App\Services\Booking\OrdinaryCheckoutService $checkoutService,
    ) {}

    public function execute(CreateBookingData $data): Booking
    {
        return $this->checkoutService->checkoutV1($data);
    }
}
