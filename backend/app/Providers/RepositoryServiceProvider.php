<?php

namespace App\Providers;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\AuditLogRepositoryInterface;
use App\Contracts\Repositories\CourtBlockRepositoryInterface;
use App\Contracts\Repositories\CourtRepositoryInterface;
use App\Contracts\Repositories\DisputeRepositoryInterface;
use App\Contracts\Repositories\FinanceExceptionRepositoryInterface;
use App\Contracts\Repositories\HostKycRepositoryInterface;
use App\Contracts\Repositories\LoyaltyRuleRepositoryInterface;
use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Contracts\Repositories\PackagePaymentReceiptRepositoryInterface;
use App\Contracts\Repositories\PackagePaymentRequestRepositoryInterface;
use App\Contracts\Repositories\PackagePriceRepositoryInterface;
use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Contracts\Repositories\PlatformPlanRepositoryInterface;
use App\Contracts\Repositories\PlatformSubscriptionRepositoryInterface;
use App\Contracts\Repositories\PriceTableRepositoryInterface;
use App\Contracts\Repositories\ReconciliationRepositoryInterface;
use App\Contracts\Repositories\RecurringContractRepositoryInterface;
use App\Contracts\Repositories\ReservationRepositoryInterface;
use App\Contracts\Repositories\TenantMembershipPlanRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Contracts\Repositories\VenuePackageRepositoryInterface;
use App\Contracts\Repositories\VenueRepositoryInterface;
use App\Contracts\Storage\FileStorageServiceInterface;
use App\Contracts\Storage\PartnerDocumentStorageInterface;
use App\Infrastructure\Repositories\Eloquent\EloquentAuditLogRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentCourtBlockRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentCourtRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentDisputeRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentFinanceExceptionRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentHostKycRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentLoyaltyRuleRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentOrganizationLockRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentPackagePaymentReceiptRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentPackagePaymentRequestRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentPackagePriceRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentPaymentRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentPlatformPlanRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentPlatformSubscriptionRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentPriceTableRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentReconciliationRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentRecurringContractRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentReservationRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentTenantMembershipPlanRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentUserRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentVenueLifecycleRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentVenuePackageRepository;
use App\Infrastructure\Repositories\Eloquent\EloquentVenueRepository;
use App\Infrastructure\Services\DurableNotificationService;
use App\Infrastructure\Storage\FileStorageService;
use App\Infrastructure\Storage\PrivatePartnerDocumentStorage;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     */
    public array $bindings = [
        VenueRepositoryInterface::class => EloquentVenueRepository::class,
        CourtRepositoryInterface::class => EloquentCourtRepository::class,
        PriceTableRepositoryInterface::class => EloquentPriceTableRepository::class,
        CourtBlockRepositoryInterface::class => EloquentCourtBlockRepository::class,
        ReservationRepositoryInterface::class => EloquentReservationRepository::class,
        PaymentRepositoryInterface::class => EloquentPaymentRepository::class,
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
        UserRepositoryInterface::class => EloquentUserRepository::class,
        VenuePackageRepositoryInterface::class => EloquentVenuePackageRepository::class,
        PackagePriceRepositoryInterface::class => EloquentPackagePriceRepository::class,
        PackagePaymentRequestRepositoryInterface::class => EloquentPackagePaymentRequestRepository::class,
        PackagePaymentReceiptRepositoryInterface::class => EloquentPackagePaymentReceiptRepository::class,
    ];

    public function register(): void
    {
        //
    }
}
