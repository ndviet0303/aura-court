<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminContractController;
use App\Http\Controllers\Admin\AdminDisputeController;
use App\Http\Controllers\Admin\AdminKycController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Booking\AdvancedBookingController;
use App\Http\Controllers\Booking\AvailabilityController;
use App\Http\Controllers\Booking\BookingController;
use App\Http\Controllers\Booking\WaitlistController;
use App\Http\Controllers\Court\CourtController;
use App\Http\Controllers\Court\ReviewController;
use App\Http\Controllers\Court\SportZoneController;
use App\Http\Controllers\Finance\FinanceController;
use App\Http\Controllers\Finance\FinanceExceptionController;
use App\Http\Controllers\Finance\FinancialReconciliationController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Organization\OrganizationController;
use App\Http\Controllers\POS\PosController;
use App\Http\Controllers\Promotion\PromotionController;
use App\Http\Controllers\Reservation\LegacyBookingAdapterController;
use App\Http\Controllers\Reservation\V2PriceRuleController;
use App\Http\Controllers\Reservation\V2ReservationController;
use App\Http\Controllers\Social\SocialController;
use App\Http\Controllers\Tournament\TournamentController;
use App\Http\Controllers\Webhook\ProviderWebhookController;
use App\Http\Controllers\Webhook\SepayWebhookController;
use App\Http\Middleware\RequireOrganizationRole;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SimulateDemoMutations;
use App\Services\Payment\WalletPaymentService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/social-login', [AuthController::class, 'socialLogin']);
    Route::middleware('web')->group(function () {
        Route::get('auth/{provider}/redirect', [AuthController::class, 'redirectToProvider']);
        Route::get('auth/{provider}/callback', [AuthController::class, 'handleProviderCallback']);
    });
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('courts', [CourtController::class, 'index']);
    Route::get('courts/{court}/reviews', [ReviewController::class, 'index']);
    Route::get('venues/{slug}', [AdminController::class, 'showVenue']);
    Route::get('sport-zones', [SportZoneController::class, 'index']);
    Route::get('sport-zones/{sportZone}', [SportZoneController::class, 'show']);
    Route::get('availability', AvailabilityController::class);
    Route::post('webhooks/sepay', SepayWebhookController::class)->withoutMiddleware('throttle:api');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', function (Request $request, WalletPaymentService $walletService) {
            $user = $request->user();
            $wallet = $walletService->getOrCreateWallet($user->id);
            $user->wallet_balance = $wallet->balance;
            return response()->json($user);
        });
        Route::post('auth/onboarding', [AuthController::class, 'onboarding']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/email/verification-notification', function (Request $request) {
            $request->user()->sendEmailVerificationNotification();
            return response()->json(['message' => 'Email verification link sent.']);
        });
        Route::get('auth/verify-email/{id}/{hash}', function (EmailVerificationRequest $request) {
            $request->fulfill();
            return response()->json(['message' => 'Email has been verified.']);
        })->name('verification.verify');
        Route::patch('profile', function (Request $request) {
            $data = $request->validate([
                'name' => 'sometimes|string|max:255',
                'phone' => 'nullable|string|max:20',
                'avatar' => 'nullable|string|max:2048',
                'bio' => 'nullable|string|max:1000',
                'skill_level' => 'nullable|string|in:beginner,intermediate,advanced,pro',
            ]);
            $user = $request->user();
            $user->update($data);
            return response()->json($user->fresh());
        });
        Route::patch('profile/password', [AuthController::class, 'changePassword']);
        Route::get('wallet', function (Request $request, WalletPaymentService $walletService) {
            $wallet = $walletService->getOrCreateWallet($request->user()->id);
            $transactions = $wallet->transactions()->latest('id')->take(20)->get();
            return response()->json([
                'id' => 'wallet-main',
                'balance' => $wallet->balance,
                'currency' => $wallet->currency,
                'points' => (int) ($wallet->balance / 1000),
                'transactions' => $transactions,
            ]);
        });
        Route::get('wallet/balance', function (Request $request, WalletPaymentService $walletService) {
            $wallet = $walletService->getOrCreateWallet($request->user()->id);
            return response()->json(['balance' => $wallet->balance, 'currency' => $wallet->currency]);
        });
        Route::post('wallet/topup', function (Request $request, WalletPaymentService $walletService) {
            $data = $request->validate([
                'amount' => 'required|integer|min:10000|max:50000000',
            ]);
            $user = $request->user();
            $wallet = $walletService->getOrCreateWallet($user->id);
            $tx = $walletService->credit(
                $wallet,
                $data['amount'],
                'wallet_deposit',
                (int) (now()->timestamp + rand(100, 999)),
                $request->input('note', 'Nạp tiền ví Aura')
            );
            return response()->json([
                'message' => 'Nạp tiền vào ví thành công.',
                'balance' => $wallet->fresh()->balance,
                'transaction_id' => $tx->id,
            ], 201);
        });
        Route::post('wallet/credit', function (Request $request, WalletPaymentService $walletService) {
            $data = $request->validate([
                'amount' => 'required|integer|min:10000|max:50000000',
            ]);
            $user = $request->user();
            $wallet = $walletService->getOrCreateWallet($user->id);
            $tx = $walletService->credit(
                $wallet,
                $data['amount'],
                'wallet_deposit',
                (int) (now()->timestamp + rand(100, 999)),
                $request->input('note', 'Nạp tiền ví Aura')
            );
            return response()->json([
                'message' => 'Nạp tiền vào ví thành công.',
                'balance' => $wallet->fresh()->balance,
                'transaction_id' => $tx->id,
            ], 201);
        });
        Route::get('bookings', [BookingController::class, 'index']);
        Route::post('bookings', [BookingController::class, 'store'])->middleware('verified');
        Route::post('bookings/recurring', [BookingController::class, 'recurring'])->middleware('verified');
        Route::get('bookings/{booking:code}', [BookingController::class, 'show']);
        Route::post('bookings/{booking:code}/sync-v2', [LegacyBookingAdapterController::class, 'sync']);
        Route::get('legacy/reservations/{reservation}', [LegacyBookingAdapterController::class, 'projection']);
        Route::post('bookings/{booking:code}/cancel', [BookingController::class, 'cancel']);
        Route::post('bookings/{booking:code}/cancel-slots', [BookingController::class, 'cancelSlots']);
        Route::post('bookings/{booking:code}/reschedule', [BookingController::class, 'reschedule']);
        Route::get('bookings/{booking:code}/payment-status', [BookingController::class, 'paymentStatus']);
        Route::get('bookings/{booking:code}/payment', [BookingController::class, 'payment']);
        Route::post('bookings/{booking:code}/coupon', [BookingController::class, 'applyCoupon']);
        Route::get('bookings/{booking:code}/calendar.ics', [BookingController::class, 'calendar']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);
        Route::get('waitlist', [WaitlistController::class, 'index']);
        Route::post('waitlist', [WaitlistController::class, 'store']);
        Route::delete('waitlist/{waitlist}', [WaitlistController::class, 'destroy']);
        Route::post('coupons/preview', [PromotionController::class, 'preview']);
        Route::get('membership', [PromotionController::class, 'membership']);
        Route::post('reviews', [ReviewController::class, 'store']);

        // Organizations endpoints
        Route::get('organizations', [OrganizationController::class, 'index']);
        Route::get('organizations/{organization}', [OrganizationController::class, 'show']);
        Route::patch('organizations/{organization}', [OrganizationController::class, 'update']);
        Route::get('organizations/{organization}/members', [OrganizationController::class, 'members']);
        Route::post('organizations/{organization}/members', [OrganizationController::class, 'addMember']);
        Route::patch('organizations/{organization}/members/{member}', [OrganizationController::class, 'updateMemberRole']);
        Route::delete('organizations/{organization}/members/{member}', [OrganizationController::class, 'removeMember']);

        Route::prefix('admin')->middleware(['admin', SimulateDemoMutations::class])->group(function () {
            Route::get('venues', [AdminController::class, 'venues']);
            Route::post('venues', [AdminController::class, 'storeVenue']);
            Route::patch('venues/{venue}', [AdminController::class, 'updateVenue']);
            Route::delete('venues/{venue}', [AdminController::class, 'destroyVenue']);
            Route::apiResource('sport-zones', SportZoneController::class)->except(['create', 'edit']);
            Route::get('courts', [CourtController::class, 'adminIndex']);
            Route::apiResource('courts', CourtController::class)->except(['index', 'show']);
            Route::get('bookings', [AdminController::class, 'bookings']);
            Route::patch('bookings/{booking}/no-show', [AdminController::class, 'noShow']);
            Route::get('payments', [AdminController::class, 'payments']);
            Route::patch('payments/{payment}', [AdminController::class, 'reviewPayment']);
            Route::post('courts/{court}/prices', [AdminController::class, 'prices']);
            Route::post('courts/{court}/prices/bulk', [AdminController::class, 'bulkPrices']);
            Route::patch('courts/{court}/prices/{price}', [AdminController::class, 'updatePrice']);
            Route::delete('courts/{court}/prices/{price}', [AdminController::class, 'destroyPrice']);
            Route::post('courts/{court}/blocks', [AdminController::class, 'blocks']);
            Route::get('courts/{court}/blocks', [AdminController::class, 'blocksIndex']);
            Route::delete('courts/{court}/blocks/{block}', [AdminController::class, 'destroyBlock']);
            Route::get('refunds', [AdminController::class, 'refunds']);
            Route::patch('refunds/{refund}', [AdminController::class, 'processRefund']);
            Route::get('reports', [AdminController::class, 'report']);
            Route::get('reports.csv', [AdminController::class, 'reportCsv']);
            Route::get('audit-logs', [AuditLogController::class, 'index']);
            Route::get('coupons', [PromotionController::class, 'coupons']);
            Route::post('coupons', [PromotionController::class, 'storeCoupon']);
            Route::get('membership-plans', [PromotionController::class, 'plans']);
            Route::post('membership-plans', [PromotionController::class, 'storePlan']);
            Route::post('memberships', [PromotionController::class, 'assignMembership']);
            Route::get('kyc', [AdminKycController::class, 'index']);
            Route::get('kyc/stats', [AdminKycController::class, 'stats']);
            Route::get('kyc/{id}', [AdminKycController::class, 'show']);
            Route::post('kyc/{id}/review', [AdminKycController::class, 'review']);
            Route::get('disputes', [AdminDisputeController::class, 'index']);
            Route::get('disputes/stats', [AdminDisputeController::class, 'stats']);
            Route::get('disputes/{id}', [AdminDisputeController::class, 'show']);
            Route::post('disputes/{id}/arbitrate', [AdminDisputeController::class, 'arbitrate']);
            Route::get('contracts', [AdminContractController::class, 'index']);
            Route::post('contracts', [AdminContractController::class, 'store']);
            Route::get('contracts/{id}', [AdminContractController::class, 'show']);
            Route::post('contracts/{id}/cancel', [AdminContractController::class, 'cancel']);
            Route::post('contracts/{id}/invoices/{invoiceId}/pay', [AdminContractController::class, 'payInvoice']);
            Route::get('contracts.csv', [AdminContractController::class, 'exportCsv']);
            Route::post('upload', [AdminController::class, 'upload']);
        });
    });
});

// v2 API Route Group with Tenant Isolation Middleware
Route::prefix('v2')->middleware([ResolveTenantContext::class])->group(function () {
    Route::post('webhooks/{provider}', ProviderWebhookController::class)->withoutMiddleware('throttle:api');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('organizations', [OrganizationController::class, 'index']);
        Route::get('organizations/{organization}', [OrganizationController::class, 'show']);
        Route::patch('organizations/{organization}', [OrganizationController::class, 'update']);
        Route::get('organizations/{organization}/members', [OrganizationController::class, 'members']);
        Route::post('organizations/{organization}/members', [OrganizationController::class, 'addMember']);
        Route::patch('organizations/{organization}/members/{member}', [OrganizationController::class, 'updateMemberRole']);
        Route::delete('organizations/{organization}/members/{member}', [OrganizationController::class, 'removeMember']);

        // Reservation commands are the canonical v2 write path. Each command
        // controller delegates to IdempotencyService and persists its outcome.
        Route::get('reservations', [V2ReservationController::class, 'index']);
        Route::post('price-quotes', [V2ReservationController::class, 'quote']);
        Route::get('price-rules', [V2PriceRuleController::class, 'index']);
        Route::get('pricing/calendar', [V2ReservationController::class, 'pricingCalendar']);
        Route::post('price-rules', [V2PriceRuleController::class, 'store']);
        Route::patch('price-rules/{rule}', [V2PriceRuleController::class, 'update']);
        Route::delete('price-rules/{rule}', [V2PriceRuleController::class, 'destroy']);
        Route::post('reservations', [V2ReservationController::class, 'store']);
        Route::get('reservations/{reservation}', [V2ReservationController::class, 'show']);
        Route::post('reservations/{reservation}/confirm', [V2ReservationController::class, 'confirm']);
        Route::post('reservations/{reservation}/cancel', [V2ReservationController::class, 'cancel']);

        Route::get('recurring-series', [AdvancedBookingController::class, 'series']);
        Route::post('recurring-series', [AdvancedBookingController::class, 'createSeries']);
        Route::get('monthly-invoices/{invoice}', [AdvancedBookingController::class, 'invoice']);
        Route::post('monthly-invoices/{invoice}/pay', [AdvancedBookingController::class, 'payInvoice']);
        Route::middleware('feature:marketplace_split_payments')->group(function () {
            Route::post('reservations/{reservation}/split', [AdvancedBookingController::class, 'createSplit']);
            Route::get('split-groups/{split}', [AdvancedBookingController::class, 'split']);
            Route::post('split-shares/{share}/pay', [AdvancedBookingController::class, 'paySplitShare']);
            Route::post('split-groups/{split}/cover-shortfall', [AdvancedBookingController::class, 'coverSplit']);
        });

        Route::middleware('feature:marketplace_slot_resale')->group(function () {
            Route::get('marketplace/listings', [AdvancedBookingController::class, 'listings']);
            Route::post('marketplace/entitlements/{entitlement}/list', [AdvancedBookingController::class, 'createListing']);
            Route::post('marketplace/listings/{listing}/purchase', [AdvancedBookingController::class, 'purchaseListing']);
            Route::post('marketplace/listings/{listing}/withdraw', [AdvancedBookingController::class, 'withdrawListing']);
        });

        Route::middleware('feature:social_matchmaking_tournaments')->group(function () {
            Route::get('social/profile', [SocialController::class, 'profile']);
            Route::put('social/profile', [SocialController::class, 'updateProfile']);
            Route::post('social/matches', [SocialController::class, 'create']);
            Route::post('social/matches/{post}/invite', [SocialController::class, 'invite']);
            Route::post('social/matches/{post}/join', [SocialController::class, 'join']);
            Route::post('social/invitations/{invitation}/respond', [SocialController::class, 'respond']);
            Route::post('social/reputation/activity', [SocialController::class, 'recordActivity'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,staff');
            Route::post('social/reputation/activities/{activity}/dispute', [SocialController::class, 'fileDispute']);
            Route::post('social/reputation/disputes/{dispute}/resolve', [SocialController::class, 'resolveDispute'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager');
            Route::post('tournaments', [TournamentController::class, 'create'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,staff');
            Route::post('tournaments/{tournament}/regenerate', [TournamentController::class, 'regenerateBracket'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,staff');
            Route::post('tournaments/{tournament}/participants', [TournamentController::class, 'addParticipant'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,staff');
            Route::post('tournament-participants/{participant}/check-in', [TournamentController::class, 'checkIn']);
            Route::post('tournaments/{tournament}/fixtures/{fixture}/score', [TournamentController::class, 'score'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,staff');
        });

        Route::middleware('feature:tenant_pos_and_wallet')->group(function () {
            Route::get('pos/products', [PosController::class, 'products']);
            Route::post('pos/products', [PosController::class, 'storeProduct'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,cashier,staff');
            Route::post('pos/products/{product}/stock', [PosController::class, 'adjustStock'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,cashier,staff');
            Route::post('pos/orders', [PosController::class, 'completeOrder'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,cashier,staff');
            Route::get('pos/shifts', [PosController::class, 'shifts'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,cashier,staff');
            Route::get('pos/shifts/active', [PosController::class, 'activeShift'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,cashier,staff');
            Route::post('pos/shifts', [PosController::class, 'openShift'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,cashier');
            Route::post('pos/shifts/{shift}/close', [PosController::class, 'closeShift'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,cashier');
            Route::post('pos/shifts/{shift}/review', [PosController::class, 'reviewReconciliation'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager');
            Route::get('memberships/plans', [PosController::class, 'membershipPlans']);
            Route::get('memberships/benefits', [PosController::class, 'benefits']);
            Route::post('memberships/plans', [PosController::class, 'publishMembershipPlan'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager');
            Route::post('memberships', [PosController::class, 'assignMembership'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,cashier');
            Route::post('loyalty/rules', [PosController::class, 'publishLoyaltyRule'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager');
            Route::post('loyalty/redeem', [PosController::class, 'redeemPoints']);
            Route::get('wallet', [PosController::class, 'wallet']);
            Route::post('wallet/credit', [PosController::class, 'creditWallet'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,cashier');
            Route::post('wallet/spend', [PosController::class, 'spendWallet']);
        });

        Route::middleware([RequireOrganizationRole::class.':organization_owner,venue_manager', 'feature:marketplace_payouts'])->group(function () {
            Route::get('finance/beneficiaries', [FinanceController::class, 'beneficiaries']);
            Route::post('finance/beneficiaries', [FinanceController::class, 'storeBeneficiary']);
            Route::get('finance/settlements', [FinanceController::class, 'settlements']);
            Route::post('finance/settlements/snapshot', [FinanceController::class, 'snapshotSettlement']);
            Route::get('finance/payouts', [FinanceController::class, 'payouts']);
            Route::post('finance/payouts', [FinanceController::class, 'createPayout']);
            Route::post('finance/payouts/{payout}/approve', [FinanceController::class, 'approvePayout']);
            Route::post('finance/payouts/{payout}/submit', [FinanceController::class, 'submitPayout']);
            Route::post('finance/payouts/{payout}/status', [FinanceController::class, 'payoutStatus']);
            Route::get('finance/exceptions/stats', [FinanceExceptionController::class, 'stats']);
            Route::get('finance/exceptions', [FinanceExceptionController::class, 'index']);
            Route::post('finance/exceptions/{id}/resolve', [FinanceExceptionController::class, 'resolve']);
            Route::get('finance/audit-logs', [FinanceExceptionController::class, 'auditLogs']);
            Route::get('finance/reconciliation/stats', [FinancialReconciliationController::class, 'stats']);
            Route::get('finance/reconciliation/runs', [FinancialReconciliationController::class, 'index']);
            Route::get('finance/reconciliation/runs/{id}', [FinancialReconciliationController::class, 'show']);
            Route::post('finance/reconciliation/trigger', [FinancialReconciliationController::class, 'trigger']);
            Route::post('finance/reconciliation/auto-resolve', [FinancialReconciliationController::class, 'autoResolve']);
            Route::get('admin/kyc', [AdminKycController::class, 'index']);
            Route::get('admin/kyc/stats', [AdminKycController::class, 'stats']);
            Route::get('admin/kyc/{id}', [AdminKycController::class, 'show']);
            Route::post('admin/kyc/{id}/review', [AdminKycController::class, 'review']);
            Route::get('admin/disputes', [AdminDisputeController::class, 'index']);
            Route::get('admin/disputes/stats', [AdminDisputeController::class, 'stats']);
            Route::get('admin/disputes/{id}', [AdminDisputeController::class, 'show']);
            Route::post('admin/disputes/{id}/arbitrate', [AdminDisputeController::class, 'arbitrate']);
        });
    });

    // Public Discoverable Routes (Tournaments & Matchmaking)
    Route::middleware('feature:social_matchmaking_tournaments')->group(function () {
        Route::get('tournaments', [TournamentController::class, 'index']);
        Route::get('tournaments/{tournament}', [TournamentController::class, 'show']);
        Route::get('tournaments/{tournament}/events', [TournamentController::class, 'events']);
        Route::get('tournaments/{tournament}/poll', [TournamentController::class, 'poll']);
        Route::get('social/matches', [SocialController::class, 'index']);
        Route::get('social/matches/{post}', [SocialController::class, 'show']);
    });
});

