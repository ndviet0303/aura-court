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
use App\Contracts\Repositories\DisputeRepositoryInterface;
use App\Infrastructure\Repositories\Eloquent\EloquentDisputeRepository;
use App\Contracts\Repositories\AuditLogRepositoryInterface;
use App\Contracts\Repositories\FinanceExceptionRepositoryInterface;
use App\Infrastructure\Repositories\Eloquent\EloquentAuditLogRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentFinanceExceptionRepository;
use App\Contracts\Repositories\HostKycRepositoryInterface;
use App\Infrastructure\Repositories\Eloquent\EloquentHostKycRepository;
use App\Contracts\Repositories\ReconciliationRepositoryInterface;
use App\Infrastructure\Repositories\Eloquent\EloquentReconciliationRepository;
use App\Contracts\Repositories\RecurringContractRepositoryInterface;
use App\Infrastructure\Repositories\Eloquent\EloquentRecurringContractRepository;
use App\Contracts\Repositories\TenantMembershipPlanRepositoryInterface;
use App\Infrastructure\Repositories\Eloquent\EloquentTenantMembershipPlanRepository;
use App\Contracts\Repositories\LoyaltyRuleRepositoryInterface;
use App\Infrastructure\Repositories\Eloquent\EloquentLoyaltyRuleRepository;
use App\Infrastructure\Storage\FileStorageService;
use App\Contracts\Storage\PartnerDocumentStorageInterface;
use App\Infrastructure\Storage\PrivatePartnerDocumentStorage;
use App\Contracts\Repositories\PlatformPlanRepositoryInterface;
use App\Infrastructure\Repositories\Eloquent\EloquentPlatformPlanRepository;
use App\Contracts\Repositories\PlatformSubscriptionRepositoryInterface;
use App\Infrastructure\Repositories\Eloquent\EloquentPlatformSubscriptionRepository;
use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Infrastructure\Repositories\Eloquent\EloquentVenueLifecycleRepository;
use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Infrastructure\Services\DurableNotificationService;
use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Infrastructure\Repositories\Eloquent\EloquentOrganizationLockRepository;
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
        ReconciliationRepositoryInterface::class => EloquentReconciliationRepository::class,
        DisputeRepositoryInterface::class => EloquentDisputeRepository::class,
        RecurringContractRepositoryInterface::class => EloquentRecurringContractRepository::class,
        TenantMembershipPlanRepositoryInterface::class => EloquentTenantMembershipPlanRepository::class,
        LoyaltyRuleRepositoryInterface::class => EloquentLoyaltyRuleRepository::class,
        FileStorageServiceInterface::class => FileStorageService::class,
        PlatformPlanRepositoryInterface::class => EloquentPlatformPlanRepository::class,
        PlatformSubscriptionRepositoryInterface::class => EloquentPlatformSubscriptionRepository::class,
        VenueLifecycleRepositoryInterface::class => EloquentVenueLifecycleRepository::class,
        OrganizationLockRepositoryInterface::class => EloquentOrganizationLockRepository::class,
        PartnerDocumentStorageInterface::class => PrivatePartnerDocumentStorage::class,
        DurableNotificationServiceInterface::class => DurableNotificationService::class,
        \App\Contracts\Repositories\UserRepositoryInterface::class => \App\Infrastructure\Repositories\Eloquent\EloquentUserRepository::class,
    ];

    public function register(): void
    {
        //
    }
}
