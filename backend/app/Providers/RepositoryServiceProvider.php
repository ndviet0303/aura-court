<?php

namespace App\Providers;

use App\Contracts\Repositories\BookingRepositoryInterface;
use App\Contracts\Repositories\CourtBlockRepositoryInterface;
use App\Contracts\Repositories\CourtPriceSlotRepositoryInterface;
use App\Contracts\Repositories\CourtRepositoryInterface;
use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Contracts\Repositories\RefundRepositoryInterface;
use App\Contracts\Repositories\VenueRepositoryInterface;
use App\Contracts\Storage\FileStorageServiceInterface;
use App\Infrastructure\Repositories\Eloquent\EloquentBookingRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentCourtBlockRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentCourtPriceSlotRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentCourtRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentPaymentRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentRefundRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentVenueRepository;
use App\Contracts\Repositories\AuditLogRepositoryInterface;
use App\Contracts\Repositories\FinanceExceptionRepositoryInterface;
use App\Infrastructure\Repositories\Eloquent\EloquentAuditLogRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentFinanceExceptionRepository;
use App\Contracts\Repositories\HostKycRepositoryInterface;
use App\Infrastructure\Repositories\Eloquent\EloquentHostKycRepository;
use App\Infrastructure\Storage\FileStorageService;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     */
    public array $bindings = [
        VenueRepositoryInterface::class => EloquentVenueRepository::class,
        CourtRepositoryInterface::class => EloquentCourtRepository::class,
        CourtPriceSlotRepositoryInterface::class => EloquentCourtPriceSlotRepository::class,
        CourtBlockRepositoryInterface::class => EloquentCourtBlockRepository::class,
        BookingRepositoryInterface::class => EloquentBookingRepository::class,
        PaymentRepositoryInterface::class => EloquentPaymentRepository::class,
        RefundRepositoryInterface::class => EloquentRefundRepository::class,
        FinanceExceptionRepositoryInterface::class => EloquentFinanceExceptionRepository::class,
        AuditLogRepositoryInterface::class => EloquentAuditLogRepository::class,
        HostKycRepositoryInterface::class => EloquentHostKycRepository::class,
        FileStorageServiceInterface::class => FileStorageService::class,
    ];

    public function register(): void
    {
        //
    }
}
