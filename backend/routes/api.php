<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminContractController;
use App\Http\Controllers\Admin\AdminDisputeController;
use App\Http\Controllers\Admin\AdminKycController;
use App\Http\Controllers\Admin\AdminVenuePublicationController;
use App\Http\Controllers\Admin\PartnerReviewController;
use App\Http\Controllers\Subscription\PlatformSubscriptionController;
use App\Http\Controllers\Partner\PartnerVenueController;
use App\Http\Controllers\Admin\AdminMembershipController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Booking\AdvancedBookingController;
use App\Http\Controllers\Booking\AvailabilityController;
use App\Http\Controllers\Booking\BookingController;
use App\Http\Controllers\Booking\WaitlistController;
use App\Http\Controllers\Customer\CustomerController;
use App\Http\Controllers\Court\CourtController;
use App\Http\Controllers\Court\ReviewController;
use App\Http\Controllers\Court\SportZoneController;
use App\Http\Controllers\Finance\FinanceController;
use App\Http\Controllers\Finance\FinanceExceptionController;
use App\Http\Controllers\Finance\FinancialReconciliationController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Organization\OrganizationController;
use App\Http\Controllers\POS\PosController;
use App\Http\Controllers\POS\ServiceTabController;
use App\Http\Controllers\Promotion\PromotionController;
use App\Http\Controllers\Reservation\LegacyBookingAdapterController;
use App\Http\Controllers\Reservation\V2PriceRuleController;
use App\Http\Controllers\Reservation\V2ReservationController;
use App\Http\Controllers\Social\CommunityFeedController;
use App\Http\Controllers\Social\FriendshipController;
use App\Http\Controllers\Social\SocialController;
use App\Http\Controllers\Tournament\TournamentController;
use App\Http\Controllers\Loyalty\LoyaltyController;
use App\Http\Controllers\Wallet\WalletDepositController;
use App\Http\Controllers\Webhook\ProviderWebhookController;
use App\Http\Controllers\Webhook\SepayWebhookController;
use App\Http\Middleware\RequireOrganizationRole;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SimulateDemoMutations;
use App\Services\Payment\WalletPaymentService;
use App\Http\Controllers\Partner\PartnerApplicationController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::get('platform/plans', [\App\Http\Controllers\Subscription\PlatformPlanController::class, 'index']);
    Route::middleware('web')->group(function () {
        Route::get('auth/{provider}/redirect', [AuthController::class, 'redirectToProvider']);
        Route::get('auth/{provider}/callback', [AuthController::class, 'handleProviderCallback']);
    });
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('partner/documents/download', [\App\Http\Controllers\Partner\PartnerDocumentController::class, 'download'])
        ->middleware('signed');
    Route::get('auth/verify-email/{id}/{hash}', function (Request $request, string $id, string $hash) {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['message' => __('auth.user_not_found')], 404);
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $hash)) {
            return response()->json(['message' => __('auth.invalid_verification_link')], 403);
        }

        if (! $request->hasValidSignature()) {
            return response()->json(['message' => __('auth.expired_verification_link')], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new \Illuminate\Auth\Events\Verified($user));
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => __('auth.email_verified_successfully')]);
        }
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        return redirect()->away($frontend.'/login?verified=1');
    })->name('verification.verify');
    Route::get('courts', [CourtController::class, 'index']);
    Route::get('courts/{court}', [CourtController::class, 'show']);
    Route::get('courts/{court}/reviews', [ReviewController::class, 'index']);
    Route::get('venues', [AdminController::class, 'venues']);
    Route::get('venues/{slug}', [AdminController::class, 'showVenue']);
    Route::get('sport-zones', [SportZoneController::class, 'index']);
    Route::get('sport-zones/{sportZone}', [SportZoneController::class, 'show']);
    Route::get('availability', AvailabilityController::class);
    Route::post('webhooks/sepay', SepayWebhookController::class)->withoutMiddleware('throttle:api');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', function (Request $request, WalletPaymentService $walletService) {
            $user = $request->user();
            $wallet = $walletService->getOrCreateWallet($user->id);

            $memberships = $user->organizationMembers()
                ->where('status', 'active')
                ->with('organization')
                ->get();

            $ownerMemberships = $memberships->filter(fn ($m) => in_array($m->role, ['organization_owner', 'venue_manager']));
            $primaryOrg = $ownerMemberships->first()?->organization;

            return response()->json(array_merge($user->toArray(), [
                'wallet_balance' => $wallet->balance,
                'organizations' => $memberships->map(fn ($m) => [
                    'organization_id' => $m->organization_id,
                    'organization_name' => $m->organization?->name,
                    'role' => $m->role,
                    'status' => $m->status,
                    'partner_status' => $m->organization?->partner_status,
                ])->values(),
                'capabilities' => [
                    'is_platform_admin' => $user->isPlatformAdmin(),
                    'can_manage_venues' => $ownerMemberships->isNotEmpty(),
                    'active_organization_id' => $primaryOrg?->id,
                    'partner_status' => $primaryOrg?->partner_status,
                ],
            ]));
        });
        Route::post('auth/onboarding', [AuthController::class, 'onboarding']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/email/verification-notification', function (Request $request) {
            if ($request->user()->hasVerifiedEmail()) {
                return response()->json(['message' => 'Email đã được xác thực trước đó.']);
            }
            $request->user()->sendEmailVerificationNotification();
            return response()->json(['message' => 'Liên kết xác thực email đã được gửi.']);
        });
        // Partner registration routes
        Route::prefix('partner')->group(function () {
            Route::get('application/current', [PartnerApplicationController::class, 'current']);
            Route::get('application/{id}', [PartnerApplicationController::class, 'show']);
            Route::post('application/start', [PartnerApplicationController::class, 'start']);
            Route::put('application/{id}', [PartnerApplicationController::class, 'save']);
            Route::post('application/{id}/documents', [\App\Http\Controllers\Partner\PartnerDocumentController::class, 'upload']);
            Route::get('application/{id}/completeness', [PartnerApplicationController::class, 'completeness']);
            Route::post('application/{id}/submit', [PartnerApplicationController::class, 'submit']);
            Route::get('application/{id}/history', [PartnerApplicationController::class, 'history']);
        });
        Route::prefix('partner/organizations/{organizationId}/subscription')->group(function () {
            Route::get('current', [PlatformSubscriptionController::class, 'current']);
            Route::post('select-plan', [PlatformSubscriptionController::class, 'selectPlan']);
            Route::post('activate-trial', [PlatformSubscriptionController::class, 'activateTrial']);
            Route::post('initiate-payment', [PlatformSubscriptionController::class, 'initiatePayment']);
            Route::post('confirm-payment', [PlatformSubscriptionController::class, 'confirmPayment']);
            Route::post('cancel-renewal', [PlatformSubscriptionController::class, 'cancelRenewal']);
            Route::post('renew', [PlatformSubscriptionController::class, 'renew']);
        });
        Route::prefix('partner/organizations/{organizationId}/venues')->group(function () {
            Route::get('', [PartnerVenueController::class, 'index']);
            Route::post('', [PartnerVenueController::class, 'store']);
            Route::get('{venueId}', [PartnerVenueController::class, 'show']);
            Route::put('{venueId}', [PartnerVenueController::class, 'update']);
            Route::get('{venueId}/readiness', [PartnerVenueController::class, 'readiness']);
            Route::post('{venueId}/submit-publication', [PartnerVenueController::class, 'submitPublication']);
            Route::post('{venueId}/operational-status', [PartnerVenueController::class, 'transitionLifecycle']);
            Route::post('{venueId}/lifecycle', [PartnerVenueController::class, 'transitionLifecycle']);
        });
        Route::post('partner/subscription/confirm-payment', [PlatformSubscriptionController::class, 'confirmPayment']);
        Route::patch('profile', function (Request $request) {
            $data = $request->validate([
                'name' => 'sometimes|string|max:255',
                'phone' => 'nullable|string|max:20',
                'avatar_url' => 'nullable|string|max:2048',
                'avatar' => 'nullable|string|max:2048',
                'play_level' => 'nullable|string|in:beginner,intermediate,advanced,pro',
                'skill_level' => 'nullable|string|in:beginner,intermediate,advanced,pro',
                'favorite_sports' => 'nullable|array',
                'favorite_sports.*' => 'string|max:50',
            ]);

            $user = $request->user();
            $update = [];
            if (array_key_exists('name', $data)) $update['name'] = $data['name'];
            if (array_key_exists('phone', $data)) $update['phone'] = $data['phone'];
            if (array_key_exists('avatar_url', $data)) $update['avatar_url'] = $data['avatar_url'];
            elseif (array_key_exists('avatar', $data)) $update['avatar_url'] = $data['avatar'];
            if (array_key_exists('play_level', $data)) $update['play_level'] = $data['play_level'];
            elseif (array_key_exists('skill_level', $data)) $update['play_level'] = $data['skill_level'];
            if (array_key_exists('favorite_sports', $data)) $update['favorite_sports'] = $data['favorite_sports'];

            if (! empty($update)) {
                $user->update($update);
            }

            return response()->json($user->fresh());
        });
        Route::patch('profile/password', [AuthController::class, 'changePassword']);
        Route::get('wallet', function (Request $request, WalletPaymentService $walletService) {
            $orgId = $request->query('organization_id') ? (int) $request->query('organization_id') : null;
            $wallet = $walletService->getOrCreateWallet($request->user()->id, $orgId);
            $transactions = $wallet->transactions()->latest('id')->take(20)->get();
            return response()->json([
                'id' => $orgId ? "wallet-org-{$wallet->organization_id}" : 'wallet-main',
                'organization_id' => $wallet->organization_id,
                'balance' => $wallet->balance,
                'currency' => $wallet->currency,
                'points' => (int) ($wallet->balance / 1000),
                'transactions' => $transactions,
            ]);
        });
        Route::get('wallet/balance', function (Request $request, WalletPaymentService $walletService) {
            $orgId = $request->query('organization_id') ? (int) $request->query('organization_id') : null;
            $wallet = $walletService->getOrCreateWallet($request->user()->id, $orgId);
            return response()->json([
                'balance' => $wallet->balance,
                'currency' => $wallet->currency,
                'organization_id' => $wallet->organization_id,
            ]);
        });
        Route::get('wallet/deposits', [WalletDepositController::class, 'index']);
        Route::post('wallet/deposits', [WalletDepositController::class, 'store']);
        Route::get('wallet/deposits/{reference}', [WalletDepositController::class, 'show']);
        Route::get('bookings', [BookingController::class, 'index']);
        Route::post('bookings', [BookingController::class, 'store'])->middleware('verified');
        Route::post('bookings/recurring', [BookingController::class, 'recurring'])->middleware('verified');
        Route::get('bookings/{booking:code}', [BookingController::class, 'show']);
        Route::post('bookings/{booking:code}/sync-v2', [LegacyBookingAdapterController::class, 'sync']);
        Route::get('legacy/reservations/{reservation}', [LegacyBookingAdapterController::class, 'projection']);
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
        Route::get('organizations/{organization}/capabilities', [OrganizationController::class, 'capabilities']);
        Route::prefix('admin')->middleware(['admin', SimulateDemoMutations::class])->group(function () {
            Route::get('venues', [AdminController::class, 'venues']);
            Route::post('venues', [AdminController::class, 'storeVenue']);
            Route::patch('venues/{venue}', [AdminController::class, 'updateVenue']);
            Route::delete('venues/{venue}', [AdminController::class, 'destroyVenue']);
            Route::prefix('venues')->group(function () {
                Route::get('publication-queue', [AdminVenuePublicationController::class, 'queue']);
                Route::get('revisions/{revisionId}', [AdminVenuePublicationController::class, 'show']);
                Route::post('revisions/{revisionId}/review', [AdminVenuePublicationController::class, 'review']);
                Route::post('{venueId}/lifecycle', [AdminVenuePublicationController::class, 'transitionLifecycle']);
            });
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
            // Admin partner application review and lifecycle management
            Route::prefix('partner')->group(function () {
                Route::get('applications', [PartnerReviewController::class, 'index']);
                Route::get('applications/{id}', [PartnerReviewController::class, 'show']);
                Route::post('applications/{id}/review', [PartnerReviewController::class, 'review']);
                Route::post('applications/{id}/clear-investigation', [PartnerReviewController::class, 'clearInvestigation']);
                Route::post('{organizationId}/suspend', [PartnerReviewController::class, 'suspend']);
                Route::post('{organizationId}/restore', [PartnerReviewController::class, 'restore']);
                Route::post('{organizationId}/terminate', [PartnerReviewController::class, 'terminate']);
            });
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
        Route::get('capabilities', [OrganizationController::class, 'capabilities']);
        Route::get('organizations/{organization}/capabilities', [OrganizationController::class, 'capabilities']);
        Route::get('customers', [CustomerController::class, 'index']);
        Route::post('customers', [CustomerController::class, 'store'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,staff,cashier');
        Route::get('customers/{customer}', [CustomerController::class, 'show']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);

        Route::get('wallet/deposits', [WalletDepositController::class, 'index']);
        Route::post('wallet/deposits', [WalletDepositController::class, 'store']);
        Route::get('wallet/deposits/{reference}', [WalletDepositController::class, 'show']);
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
        Route::post('reservations/checkout', [V2ReservationController::class, 'checkout']);
        Route::get('reservations/{reservation}', [V2ReservationController::class, 'show']);
        Route::post('reservations/{reservation}/confirm', [V2ReservationController::class, 'confirm']);
        Route::post('loyalty/preview', [LoyaltyController::class, 'preview']);
        Route::post('loyalty/redeem', [LoyaltyController::class, 'redeem']);

        Route::middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,staff,cashier')->group(function () {
            Route::post('attendance/check-in', [AttendanceController::class, 'checkIn']);
            Route::post('attendance/check-out', [AttendanceController::class, 'checkOut']);
            Route::get('attendance/{code}', [AttendanceController::class, 'show']);

            Route::get('service-tabs', [ServiceTabController::class, 'index']);
            Route::post('service-tabs', [ServiceTabController::class, 'store']);
            Route::get('service-tabs/{reservation}', [ServiceTabController::class, 'show']);
            Route::delete('service-tabs/{reservation}/items/{item}', [ServiceTabController::class, 'removeItem']);
            Route::post('service-tabs/{tab}/settle', [ServiceTabController::class, 'settle']);
            Route::post('service-tabs/{tab}/cancel', [ServiceTabController::class, 'cancel']);
        });

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
            // Friendships & Connections
            Route::get('social/friends', [FriendshipController::class, 'index']);
            Route::get('social/friends/requests', [FriendshipController::class, 'requests']);
            Route::post('social/friends/requests', [FriendshipController::class, 'sendRequest']);
            Route::post('social/friends/requests/{friendship}/accept', [FriendshipController::class, 'accept']);
            Route::post('social/friends/requests/{friendship}/decline', [FriendshipController::class, 'decline']);
            Route::delete('social/friends/{friendship}', [FriendshipController::class, 'destroy']);
            // Community Feed, Comments, Reactions & Moderation
            Route::get('social/feed', [CommunityFeedController::class, 'index']);
            Route::post('social/feed', [CommunityFeedController::class, 'store']);
            Route::get('social/feed/reports', [CommunityFeedController::class, 'reports'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager');
            Route::get('social/feed/reports/{report}', [CommunityFeedController::class, 'showReport'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager');
            Route::get('social/feed/{post}', [CommunityFeedController::class, 'show']);
            Route::put('social/feed/{post}', [CommunityFeedController::class, 'update']);
            Route::delete('social/feed/{post}', [CommunityFeedController::class, 'destroy']);
            Route::post('social/feed/{post}/comments', [CommunityFeedController::class, 'storeComment']);
            Route::put('social/feed/comments/{comment}', [CommunityFeedController::class, 'updateComment']);
            Route::delete('social/feed/comments/{comment}', [CommunityFeedController::class, 'destroyComment']);
            Route::post('social/feed/{post}/react', [CommunityFeedController::class, 'reactPost']);
            Route::post('social/feed/comments/{comment}/react', [CommunityFeedController::class, 'reactComment']);
            Route::post('social/feed/{post}/report', [CommunityFeedController::class, 'reportPost']);
            Route::post('social/feed/comments/{comment}/report', [CommunityFeedController::class, 'reportComment']);
            Route::post('social/feed/{post}/hide', [CommunityFeedController::class, 'hidePost'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager');
            Route::post('social/feed/{post}/restore', [CommunityFeedController::class, 'restorePost'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager');
            Route::post('social/feed/comments/{comment}/hide', [CommunityFeedController::class, 'hideComment'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager');
            Route::post('social/feed/comments/{comment}/restore', [CommunityFeedController::class, 'restoreComment'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager');


            Route::post('social/matches', [SocialController::class, 'create']);
            Route::put('social/matches/{post}', [SocialController::class, 'update']);
            Route::post('social/matches/{post}/end', [SocialController::class, 'endSession']);
            Route::get('social/matches/{post}/bill', [SocialController::class, 'getBill']);
            Route::post('social/matches/{post}/bill/draft', [SocialController::class, 'saveDraftBill']);
            Route::post('social/matches/{post}/bill/finalize', [SocialController::class, 'finalizeBill']);
            Route::get('social/matches/{post}/rates', [SocialController::class, 'getDefaultRates']);
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
            Route::post('tournaments/{tournament}/register', [TournamentController::class, 'register']);
            Route::get('tournaments/{tournament}/registration', [TournamentController::class, 'registration']);
            Route::post('tournaments/{tournament}/fixtures/{fixture}/score', [TournamentController::class, 'score'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,staff');
        });

        Route::middleware('feature:tenant_pos_and_wallet')->group(function () {
            Route::get('pos/products', [PosController::class, 'products']);
            Route::get('pos/products/{product}', [PosController::class, 'showProduct']);
            Route::post('pos/products', [PosController::class, 'storeProduct'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,cashier,staff');
            Route::put('pos/products/{product}', [PosController::class, 'updateProduct'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,cashier,staff');
            Route::delete('pos/products/{product}', [PosController::class, 'destroyProduct'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager');
            Route::post('pos/products/{product}/stock', [PosController::class, 'adjustStock'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,cashier,staff');
            Route::get('pos/inventory/movements', [PosController::class, 'movements'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,cashier,staff');
            Route::get('pos/inventory/kpis', [PosController::class, 'inventoryKpis'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,cashier,staff');
            Route::post('pos/quote', [PosController::class, 'quote'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,cashier,staff');
            Route::post('pos/orders', [PosController::class, 'completeOrder'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,cashier,staff');
            Route::get('pos/orders/{order}', [PosController::class, 'showOrder'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,cashier,staff');
            Route::post('pos/orders/{order}/confirm-qr', [PosController::class, 'confirmQrPayment'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,cashier,staff');
            Route::post('pos/orders/{order}/cancel-qr', [PosController::class, 'cancelQrPayment'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,cashier,staff');
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
            Route::get('wallet', [PosController::class, 'wallet']);
            Route::post('wallet/credit', [PosController::class, 'creditWallet'])->middleware(RequireOrganizationRole::class.':organization_owner,venue_manager,cashier');
            Route::post('wallet/spend', [PosController::class, 'spendWallet']);
        });

        Route::middleware([RequireOrganizationRole::class.':organization_owner,venue_manager', 'feature:marketplace_payouts'])->group(function () {
            Route::get('finance/beneficiaries', [FinanceController::class, 'beneficiaries']);
            Route::post('finance/beneficiaries', [FinanceController::class, 'storeBeneficiary']);
            Route::get('finance/settlements', [FinanceController::class, 'settlements']);
            Route::post('finance/settlements/snapshot', [FinanceController::class, 'snapshotSettlement']);
            Route::get('finance/settlements.csv', [FinanceController::class, 'exportSettlementsCsv']);
            Route::get('finance/payouts', [FinanceController::class, 'payouts']);
            Route::post('finance/payouts', [FinanceController::class, 'createPayout']);
            Route::post('finance/payouts/{payout}/approve', [FinanceController::class, 'approvePayout']);
            Route::post('finance/payouts/{payout}/submit', [FinanceController::class, 'submitPayout']);
            Route::post('finance/payouts/{payout}/status', [FinanceController::class, 'payoutStatus']);
            Route::get('finance/payouts.csv', [FinanceController::class, 'exportPayoutsCsv']);
            Route::get('finance/exceptions.csv', [FinanceController::class, 'exportExceptionsCsv']);
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

        Route::middleware([RequireOrganizationRole::class.':organization_owner,venue_manager'])->group(function () {
            Route::get('admin/memberships/plans', [AdminMembershipController::class, 'plans']);
            Route::post('admin/memberships/plans', [AdminMembershipController::class, 'storePlan']);
            Route::patch('admin/memberships/plans/{id}', [AdminMembershipController::class, 'updatePlan']);
            Route::post('admin/memberships/plans/{id}/archive', [AdminMembershipController::class, 'archivePlan']);
            Route::get('admin/memberships/loyalty-config', [AdminMembershipController::class, 'loyaltyConfig']);
            Route::patch('admin/memberships/loyalty-config', [AdminMembershipController::class, 'updateLoyaltyConfig']);
            Route::get('admin/memberships/multiplier-rules', [AdminMembershipController::class, 'multiplierRules']);
            Route::post('admin/memberships/multiplier-rules', [AdminMembershipController::class, 'storeMultiplierRule']);
            Route::patch('admin/memberships/multiplier-rules/{id}', [AdminMembershipController::class, 'updateMultiplierRule']);
            Route::delete('admin/memberships/multiplier-rules/{id}', [AdminMembershipController::class, 'deleteMultiplierRule']);
            Route::get('admin/memberships/version-history', [AdminMembershipController::class, 'versionHistory']);
            Route::get('admin/memberships/stats', [AdminMembershipController::class, 'stats']);
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
