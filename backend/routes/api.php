<?php

use App\Http\Controllers\Admin\AdminContractController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminDisputeController;
use App\Http\Controllers\Admin\AdminKycController;
use App\Http\Controllers\Admin\AdminMembershipController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminVenueMonitoringController;
use App\Http\Controllers\Admin\AdminVenueRemediationController;
use App\Http\Controllers\Admin\AdminVenueSuspensionController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ContentModerationController;
use App\Http\Controllers\Admin\PartnerReviewController;
use App\Http\Controllers\Admin\PlatformFeePriceController;
use App\Http\Controllers\Admin\PlatformFeeReportController;
use App\Http\Controllers\Admin\PlatformFeeRequestController;
use App\Http\Controllers\Admin\PlatformFeeVenueController;
use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Attendance\OperationsController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\SocialEmailController;
use App\Http\Controllers\Booking\AdvancedBookingController;
use App\Http\Controllers\Booking\AvailabilityController;
use App\Http\Controllers\Booking\ResaleListingController;
use App\Http\Controllers\Booking\WaitlistController;
use App\Http\Controllers\Chat\ConversationController;
use App\Http\Controllers\Community\ContentReportController;
use App\Http\Controllers\Community\FeedCommentController;
use App\Http\Controllers\Community\FeedPostController;
use App\Http\Controllers\Court\CourtController;
use App\Http\Controllers\Court\CourtLifecycleController;
use App\Http\Controllers\Customer\CustomerController;
use App\Http\Controllers\Customer\VenueCustomerController;
use App\Http\Controllers\Finance\FinanceController;
use App\Http\Controllers\Finance\FinanceExceptionController;
use App\Http\Controllers\Finance\FinancialReconciliationController;
use App\Http\Controllers\Finance\OwnerPaymentExceptionController;
use App\Http\Controllers\Finance\OwnerVenueCreditController;
use App\Http\Controllers\Finance\VenueCreditController;
use App\Http\Controllers\Loyalty\LoyaltyController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Organization\InvitationResponseController;
use App\Http\Controllers\Organization\OrganizationController;
use App\Http\Controllers\Partner\OwnerFinanceController;
use App\Http\Controllers\Partner\PartnerApplicationController;
use App\Http\Controllers\Partner\PartnerDocumentController;
use App\Http\Controllers\Partner\PartnerVenueController;
use App\Http\Controllers\Partner\PartnerVenueCourtController;
use App\Http\Controllers\Partner\PartnerVenueScheduleController;
use App\Http\Controllers\Partner\ReceivingConnectionController;
use App\Http\Controllers\Partner\VenueLifecycleController;
use App\Http\Controllers\Partner\VenuePackageController;
use App\Http\Controllers\Partner\VenueScheduleImpactController;
use App\Http\Controllers\Partner\VenueSuspensionController;
use App\Http\Controllers\POS\PosController;
use App\Http\Controllers\POS\ServiceTabController;
use App\Http\Controllers\POS\StockTransferController;
use App\Http\Controllers\POS\VenueCreditCashOutController;
use App\Http\Controllers\Pricing\CourtPricingController;
use App\Http\Controllers\Pricing\PriceTableController;
use App\Http\Controllers\Promotion\PromotionProgramController;
use App\Http\Controllers\Promotion\VoucherChoiceController;
use App\Http\Controllers\Reservation\AdminReservationController;
use App\Http\Controllers\Reservation\AffectedBookingCommandController;
use App\Http\Controllers\Reservation\AffectedReservationCaseController;
use App\Http\Controllers\Reservation\CancelReservationPaymentController;
use App\Http\Controllers\Reservation\MyReservationController;
use App\Http\Controllers\Reservation\ReservationController;
use App\Http\Controllers\Review\ReviewController;
use App\Http\Controllers\Tournament\ManagedTournamentController;
use App\Http\Controllers\Tournament\PublicTournamentController;
use App\Http\Controllers\Tournament\TournamentEntryController;
use App\Http\Controllers\Tournament\TournamentFixtureController;
use App\Http\Controllers\Tournament\TournamentRosterController;
use App\Http\Controllers\Tournament\TournamentSetupController;
use App\Http\Controllers\Wallet\WalletController;
use App\Http\Controllers\Wallet\WalletDepositController;
use App\Http\Controllers\Webhook\ProviderWebhookController;
use App\Http\Controllers\Webhook\ReceivingConnectionWebhookController;
use App\Http\Controllers\Webhook\SepayWebhookController;
use App\Http\Middleware\RequireOrganizationRole;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SimulateDemoMutations;
use App\Models\HostKyc;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\Payment\WalletAvailabilityService;
use App\Services\Payment\WalletPaymentService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
Route::middleware('web')->group(function () {
    Route::get('auth/{provider}/redirect', [AuthController::class, 'redirectToProvider']);
});
Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-email-link');
Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth-reset');
Route::get('partner/documents/download', [PartnerDocumentController::class, 'download'])
    ->middleware('signed')
    ->name('partner.documents.download');
Route::get('auth/verify-email/{id}/{hash}', function (Request $request, string $id, string $hash) {
    $user = User::find($id);
    if (! $user) {
        return response()->json(['message' => __('auth.user_not_found')], 404);
    }

    // BR-05: each link works once, and sending a new link retires the older ones.
    if (! VerifyEmailNotification::matches($user, $hash)) {
        return response()->json(['message' => __('auth.invalid_verification_link')], 403);
    }

    if (! $request->hasValidSignature()) {
        return response()->json(['message' => __('auth.expired_verification_link')], 403);
    }

    VerifyEmailNotification::forget($user);
    if (! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
        event(new Verified($user));
    }

    if ($request->wantsJson()) {
        return response()->json(['message' => __('auth.email_verified_successfully')]);
    }
    $frontend = rtrim((string) config('app.frontend_url'), '/');

    return redirect()->away($frontend.'/login?verified=1');
})->name('verification.verify');
Route::get('courts', [CourtController::class, 'index']);
Route::get('courts/{court}', [CourtController::class, 'show']);
// Module 07 D12, D15: the feed is one platform-wide board and reviews belong to a venue; both read without an account.
Route::get('social/feed', [FeedPostController::class, 'index']);
Route::get('social/feed/{post}', [FeedPostController::class, 'show'])->whereNumber('post');
Route::get('venues/{venue}/reviews', [ReviewController::class, 'index'])->whereNumber('venue');
// Module 07 FR-121, FR-122: published public tournaments; an internal or draft one only for those who take part.
Route::get('tournaments', [PublicTournamentController::class, 'index']);
Route::get('tournaments/{tournament}', [PublicTournamentController::class, 'show']);
Route::get('venues', [AdminController::class, 'venues']);
Route::get('venues/{slug}', [AdminController::class, 'showVenue']);
Route::get('availability', AvailabilityController::class);
// D10, D20: what a booking form offers before checkout; neither route holds or consumes a voucher use.
Route::get('venues/{venue}/vouchers', [VoucherChoiceController::class, 'index'])->whereNumber('venue');
Route::get('venues/{venue}/vouchers/check', [VoucherChoiceController::class, 'check'])->whereNumber('venue')->middleware('throttle:voucher-check');
// Venues and courts are never deleted and venues are written only on the partner path (spec "Retired write
// endpoints", D12). Without these routes the live GET admin/venues and PATCH/PUT admin/courts/{court} would answer
// the retired calls with 405 instead of 404; admin/venues/{venue} has no route left, so it answers 404 by itself.
Route::post('admin/venues', fn () => abort(404));
Route::delete('admin/courts/{court}', fn () => abort(404));
// Google, Facebook and SePay consoles have these URLs registered, so they keep their pre-unversioning v1 paths.
Route::prefix('v1')->group(function () {
    Route::get('auth/{provider}/callback', [AuthController::class, 'handleProviderCallback'])->middleware('web');
    Route::post('webhooks/sepay', SepayWebhookController::class)->withoutMiddleware('throttle:api');
    Route::post('webhooks/sepay/connections/{routingId}', ReceivingConnectionWebhookController::class)
        ->withoutMiddleware('throttle:api')
        ->name('webhooks.sepay.connection');
});

Route::middleware(['auth:sanctum', 'not_restricted'])->group(function () {
    // Module 07 FR-118, FR-111: chat crosses organizations, so it sits outside the tenant group; access is per side.
    Route::get('conversations', [ConversationController::class, 'index']);
    Route::post('conversations', [ConversationController::class, 'store']);
    Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages']);
    Route::post('conversations/{conversation}/messages', [ConversationController::class, 'send']);
    Route::post('conversations/{conversation}/vouchers', [ConversationController::class, 'gift']);
    Route::get('venues/{venue}/gift-vouchers', [ConversationController::class, 'giftableVouchers']);
    // Module 07 FR-112..FR-117, FR-132: feed, reviews and reports cross organizations too; only authors change their content.
    Route::post('uploads/community-images', [ContentReportController::class, 'uploadImage'])->middleware('throttle:30,1');
    Route::post('social/feed', [FeedPostController::class, 'store']);
    Route::put('social/feed/{post}', [FeedPostController::class, 'update'])->whereNumber('post');
    Route::delete('social/feed/{post}', [FeedPostController::class, 'destroy'])->whereNumber('post');
    Route::post('social/feed/{post}/comments', [FeedCommentController::class, 'store'])->whereNumber('post');
    Route::put('social/feed/comments/{comment}', [FeedCommentController::class, 'update']);
    Route::delete('social/feed/comments/{comment}', [FeedCommentController::class, 'destroy']);
    Route::post('content-reports', [ContentReportController::class, 'store']);
    // Module 07 part C (D20): tournaments cross organizations; TournamentAccess checks the caller's role per tournament.
    Route::post('tournaments/{tournament}/entries', [TournamentEntryController::class, 'store']);
    Route::get('me/tournament-entries', [TournamentEntryController::class, 'mine']);
    Route::post('tournament-entries/{entry}/withdraw', [TournamentEntryController::class, 'withdraw']);
    Route::post('tournament-entries/{entry}/payment', [TournamentEntryController::class, 'pay']);
    Route::post('tournament-entries/{entry}/approve', [TournamentEntryController::class, 'approve']);
    Route::post('tournament-entries/{entry}/reject', [TournamentEntryController::class, 'reject'])->name('tournament-entries.reject');
    Route::delete('tournament-entries/{entry}', [TournamentEntryController::class, 'destroy']);
    Route::get('managed/tournaments', [ManagedTournamentController::class, 'index']);
    Route::post('managed/tournaments', [ManagedTournamentController::class, 'store']);
    Route::post('managed/internal-tournaments', [ManagedTournamentController::class, 'storeInternal']);
    Route::get('managed/tournaments/{tournament}', [ManagedTournamentController::class, 'show']);
    Route::put('managed/tournaments/{tournament}', [ManagedTournamentController::class, 'update']);
    Route::post('managed/tournaments/{tournament}/publish', [ManagedTournamentController::class, 'publish']);
    Route::post('managed/tournaments/{tournament}/stop', [ManagedTournamentController::class, 'stop']);
    Route::post('managed/tournaments/{tournament}/allocations', [TournamentSetupController::class, 'allocate']);
    Route::delete('managed/tournaments/{tournament}/allocations/{allocation}', [TournamentSetupController::class, 'release']);
    Route::post('managed/tournaments/{tournament}/grants', [TournamentSetupController::class, 'grant']);
    Route::delete('managed/tournaments/{tournament}/grants/{grant}', [TournamentSetupController::class, 'revoke']);
    Route::post('managed/tournaments/{tournament}/lock-roster', [TournamentSetupController::class, 'lockRoster']);
    Route::post('managed/tournaments/{tournament}/draw', [TournamentSetupController::class, 'draw']);
    Route::get('managed/tournaments/{tournament}/roster/template', [TournamentRosterController::class, 'template']);
    Route::post('managed/tournaments/{tournament}/roster/preview', [TournamentRosterController::class, 'preview']);
    Route::post('managed/tournaments/{tournament}/roster/confirm', [TournamentRosterController::class, 'confirm']);
    Route::post('managed/tournaments/{tournament}/roster/entries', [TournamentRosterController::class, 'store']);
    Route::put('tournament-entries/{entry}', [TournamentRosterController::class, 'update']);
    Route::put('tournament-fixtures/{fixture}/schedule', [TournamentFixtureController::class, 'schedule']);
    Route::post('tournament-fixtures/{fixture}/result', [TournamentFixtureController::class, 'result']);
    Route::post('tournament-fixtures/{fixture}/walkover', [TournamentFixtureController::class, 'result'])->name('tournament-fixtures.walkover');
    Route::post('tournament-fixtures/{fixture}/correction', [TournamentFixtureController::class, 'correct'])->name('tournament-fixtures.correction');
    Route::post('reviews', [ReviewController::class, 'store'])->middleware('verified');
    Route::put('reviews/{review}', [ReviewController::class, 'update']);
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy']);
    Route::put('reviews/{review}/reply', [ReviewController::class, 'reply']);

    Route::get('auth/me', function (Request $request, WalletPaymentService $walletService) {
        $user = $request->user();
        $wallet = $walletService->getOrCreateWallet($user->id);

        $memberships = $user->organizationMembers()
            ->where('status', 'active')
            ->with('organization')
            ->get();

        $ownerMemberships = $memberships->filter(fn ($m) => $m->role === 'organization_owner');
        $primaryOrg = $ownerMemberships->first()?->organization;
        $latestKyc = HostKyc::where('user_id', $user->id)->latest()->first();

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
                'needs_email' => $user->needsEmail(),
                'can_manage_venues' => $ownerMemberships->isNotEmpty(),
                'active_organization_id' => $primaryOrg?->id,
                'partner_status' => $primaryOrg?->partner_status ?? $latestKyc?->status,
                'has_partner_application' => $latestKyc !== null,
                'partner_application_status' => $latestKyc?->status,
                'wallet_enabled' => app(WalletAvailabilityService::class)->enabledFor($user),
            ],
        ]));
    });
    Route::post('auth/onboarding', [AuthController::class, 'onboarding']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/email/verification-notification', function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email đã được xác thực trước đó.']);
        }
        abort_if($request->user()->needsEmail(), 422, 'Vui lòng bổ sung email trước khi xác thực.');
        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Liên kết xác thực email đã được gửi.']);
    })->middleware('throttle:auth-email-link');
    // A typo must not lock the form for a minute; the email-link throttle is charged once the mail goes out.
    Route::post('auth/email', SocialEmailController::class)->middleware('throttle:6,1');
    // Partner registration routes
    Route::prefix('partner')->group(function () {
        Route::get('application/current', [PartnerApplicationController::class, 'current']);
        Route::get('application/{id}', [PartnerApplicationController::class, 'show']);
        Route::post('application/start', [PartnerApplicationController::class, 'start']);
        Route::put('application/{id}', [PartnerApplicationController::class, 'save']);
        Route::post('application/{id}/documents', [PartnerDocumentController::class, 'upload']);
        Route::get('application/{id}/completeness', [PartnerApplicationController::class, 'completeness']);
        Route::post('application/{id}/submit', [PartnerApplicationController::class, 'submit']);
        Route::get('application/{id}/history', [PartnerApplicationController::class, 'history']);
    });
    Route::prefix('partner/organizations/{organizationId}/venues')->group(function () {
        Route::get('', [PartnerVenueController::class, 'index']);
        // D12: the one venue write path; demo owners only simulate it, as on the retired admin/venues routes.
        Route::post('', [PartnerVenueController::class, 'store'])->middleware(SimulateDemoMutations::class);
        Route::get('{venueId}', [PartnerVenueController::class, 'show']);
        Route::put('{venueId}', [PartnerVenueController::class, 'update'])->middleware(SimulateDemoMutations::class);
        Route::get('{venueId}/booking-policy', [PartnerVenueScheduleController::class, 'bookingPolicy']);
        Route::put('{venueId}/booking-policy', [PartnerVenueScheduleController::class, 'saveBookingPolicy']);
        Route::get('{venueId}/operating-schedule', [PartnerVenueScheduleController::class, 'operatingSchedule']);
        Route::put('{venueId}/operating-schedule', [PartnerVenueScheduleController::class, 'saveOperatingSchedule']);
        Route::get('{venueId}/schedule-exceptions', [PartnerVenueScheduleController::class, 'scheduleExceptions']);
        Route::put('{venueId}/schedule-exceptions', [PartnerVenueScheduleController::class, 'saveScheduleException']);
        Route::delete('{venueId}/schedule-exceptions/{exceptionId}', [PartnerVenueScheduleController::class, 'deleteScheduleException']);
        // D16: demo owners only simulate package changes.
        Route::post('{venueId}/package-requests', [VenuePackageController::class, 'storeRequest'])->middleware(SimulateDemoMutations::class);
        Route::post('{venueId}/package/terminate', [VenuePackageController::class, 'terminate'])->middleware(SimulateDemoMutations::class);
        Route::get('{venueId}/courts/{courtId}', [PartnerVenueCourtController::class, 'show']);
        Route::post('{venueId}/open', [VenueLifecycleController::class, 'open']);
        Route::post('{venueId}/pause', [VenueLifecycleController::class, 'pause']);
        Route::post('{venueId}/resume', [VenueLifecycleController::class, 'resume']);
        Route::post('{venueId}/close', [VenueLifecycleController::class, 'close']);
        // D7: what a stop or schedule change would do to existing bookings, saving nothing.
        Route::post('{venueId}/pause/preview', [VenueLifecycleController::class, 'previewPause']);
        Route::post('{venueId}/close/preview', [VenueLifecycleController::class, 'previewClose']);
        Route::post('{venueId}/operating-schedule/preview', [VenueScheduleImpactController::class, 'operatingSchedule']);
        Route::post('{venueId}/schedule-exceptions/preview', [VenueScheduleImpactController::class, 'scheduleException']);
        Route::post('{venueId}/schedule-exceptions/{exceptionId}/delete-preview', [VenueScheduleImpactController::class, 'deleteScheduleException']);
        Route::get('{venueId}/suspensions', [VenueSuspensionController::class, 'index']);
        Route::get('{venueId}/suspensions/{suspensionId}', [VenueSuspensionController::class, 'show']);
        Route::post('{venueId}/suspensions/{suspensionId}/remediations', [VenueSuspensionController::class, 'storeRemediation']);
    });
    Route::get('partner/organizations/{organizationId}/packages', [VenuePackageController::class, 'index']);
    Route::get('partner/organizations/{organizationId}/package-requests/{code}', [VenuePackageController::class, 'showRequest']);
    Route::get('partner/organizations/{organizationId}/finance/transactions', [OwnerFinanceController::class, 'transactions']);
    Route::get('partner/organizations/{organizationId}/finance/report', [OwnerFinanceController::class, 'report']);
    Route::get('partner/organizations/{organizationId}/finance/report/export', [OwnerFinanceController::class, 'export']);
    Route::get('partner/organizations/{organizationId}/venues/{venueId}/receiving-connection', [ReceivingConnectionController::class, 'show']);
    Route::post('partner/organizations/{organizationId}/venues/{venueId}/receiving-connection', [ReceivingConnectionController::class, 'store']);
    Route::post('partner/organizations/{organizationId}/venues/{venueId}/receiving-connection/{connectionId}/credential', [ReceivingConnectionController::class, 'rotateCredential']);
    Route::get('partner/organizations/{organizationId}/venues/{venueId}/receiving-connection/{connectionId}/evidence', [ReceivingConnectionController::class, 'evidence']);
    Route::post('partner/organizations/{organizationId}/venues/{venueId}/receiving-connection/{connectionId}/query-credential', [ReceivingConnectionController::class, 'rotateApiToken']);
    Route::post('partner/organizations/{organizationId}/venues/{venueId}/receiving-connection/{connectionId}/transactions/sync', [ReceivingConnectionController::class, 'syncTransactions']);
    Route::post('partner/organizations/{organizationId}/venues/{venueId}/receiving-connection/{connectionId}/select', [ReceivingConnectionController::class, 'select']);
    Route::patch('profile', function (Request $request) {
        $data = $request->validate([
            'name' => 'sometimes|string|min:2|max:100',
            'phone' => ['nullable', 'string', 'regex:'.User::PHONE_PATTERN],
            'avatar_url' => 'nullable|string|max:2048',
            'avatar' => 'nullable|string|max:2048',
            'play_level' => 'nullable|string|in:beginner,intermediate,advanced,pro',
            'skill_level' => 'nullable|string|in:beginner,intermediate,advanced,pro',
            'favorite_sports' => 'nullable|array',
            'favorite_sports.*' => 'string|max:50',
        ], [
            'name.min' => 'Họ tên phải dài 2–100 ký tự.',
            'name.max' => 'Họ tên phải dài 2–100 ký tự.',
            'phone.regex' => 'Số điện thoại phải gồm 10 chữ số bắt đầu bằng 0 hoặc +84 kèm 9 chữ số.',
        ]);

        $user = $request->user();
        $update = [];
        if (array_key_exists('name', $data)) {
            $update['name'] = $data['name'];
        }
        if (array_key_exists('phone', $data)) {
            $update['phone'] = $data['phone'];
        }
        if (array_key_exists('avatar_url', $data)) {
            $update['avatar_url'] = $data['avatar_url'];
        } elseif (array_key_exists('avatar', $data)) {
            $update['avatar_url'] = $data['avatar'];
        }
        if (array_key_exists('play_level', $data)) {
            $update['play_level'] = $data['play_level'];
        } elseif (array_key_exists('skill_level', $data)) {
            $update['play_level'] = $data['skill_level'];
        }
        if (array_key_exists('favorite_sports', $data)) {
            $update['favorite_sports'] = $data['favorite_sports'];
        }

        if (! empty($update)) {
            $user->update($update);
        }

        return response()->json($user->fresh());
    });
    Route::patch('profile/password', [AuthController::class, 'changePassword']);
    Route::post('profile/password/setup-link', [AuthController::class, 'setPassword'])->middleware('throttle:auth-email-link');
    Route::get('wallet', [WalletController::class, 'show']);
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
    Route::post('wallet/deposits', [WalletDepositController::class, 'store'])->middleware('verified');
    Route::get('wallet/deposits/{reference}', [WalletDepositController::class, 'show']);
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);
    Route::get('waitlist', [WaitlistController::class, 'index']);
    Route::post('waitlist', [WaitlistController::class, 'store'])->middleware('verified');
    Route::delete('waitlist/{waitlist}', [WaitlistController::class, 'destroy']);
    Route::get('membership', [LoyaltyController::class, 'membership']);

    // Organizations endpoints
    Route::get('organizations', [OrganizationController::class, 'index']);
    Route::get('organizations/{organization}', [OrganizationController::class, 'show']);
    Route::patch('organizations/{organization}', [OrganizationController::class, 'update']);
    Route::get('organizations/{organization}/members', [OrganizationController::class, 'members']);
    Route::patch('organizations/{organization}/members/{member}', [OrganizationController::class, 'updateMemberRole']);
    Route::delete('organizations/{organization}/members/{member}', [OrganizationController::class, 'removeMember']);
    Route::get('organizations/{organization}/capabilities', [OrganizationController::class, 'capabilities']);
    Route::get('organizations/{organization}/invitations', [OrganizationController::class, 'invitations']);
    Route::post('organizations/{organization}/invitations', [OrganizationController::class, 'invite']);
    Route::post('organizations/{organization}/invitations/{invitation}/revoke', [OrganizationController::class, 'revokeInvitation']);
    Route::get('invitations', [InvitationResponseController::class, 'index']);
    Route::post('invitations/{invitation}/accept', [InvitationResponseController::class, 'accept']);
    Route::post('invitations/{invitation}/decline', [InvitationResponseController::class, 'decline']);
    Route::get('disputes', [AdminDisputeController::class, 'index']);
    Route::post('disputes', [AdminDisputeController::class, 'store'])->middleware('verified');
    Route::get('disputes/stats', [AdminDisputeController::class, 'stats']);
    Route::get('disputes/{id}', [AdminDisputeController::class, 'show']);
    Route::post('disputes/{id}/arbitrate', [AdminDisputeController::class, 'arbitrate']);
    Route::post('disputes/{id}/evidence', [AdminDisputeController::class, 'submitEvidence'])->middleware('verified');
    Route::post('disputes/{id}/reassign', [AdminDisputeController::class, 'reassign']);
    Route::post('disputes/{id}/close', [AdminDisputeController::class, 'close']);
    Route::post('disputes/{id}/reopen', [AdminDisputeController::class, 'reopen']);
    Route::prefix('admin')->middleware([SimulateDemoMutations::class])->group(function () {
        // Platform admin ONLY routes
        Route::middleware(['admin'])->group(function () {
            Route::get('platform-fees/prices', [PlatformFeePriceController::class, 'index']);
            Route::post('platform-fees/prices', [PlatformFeePriceController::class, 'store']);
            Route::get('platform-fees/requests', [PlatformFeeRequestController::class, 'index']);
            Route::get('platform-fees/requests/export', [PlatformFeeRequestController::class, 'export']);
            Route::get('platform-fees/requests/{code}', [PlatformFeeRequestController::class, 'show']);
            Route::get('platform-fees/venues', [PlatformFeeVenueController::class, 'index']);
            Route::get('platform-fees/venues/export', [PlatformFeeVenueController::class, 'export']);
            Route::get('platform-fees/venues/{venueId}', [PlatformFeeVenueController::class, 'show'])->whereNumber('venueId');
            Route::get('platform-fees/report', [PlatformFeeReportController::class, 'show']);
            Route::get('platform-fees/report/export', [PlatformFeeReportController::class, 'export']);
            // Module 07 D14: reported posts, comments and reviews; owners and staff have no moderation right.
            Route::get('content-reports', [ContentModerationController::class, 'index']);
            Route::get('content-reports/{report}', [ContentModerationController::class, 'show']);
            Route::post('content-reports/{report}/hide', [ContentModerationController::class, 'hide']);
            Route::post('content-reports/{report}/restore', [ContentModerationController::class, 'restore']);
            Route::post('content-reports/{report}/keep', [ContentModerationController::class, 'keep']);
            Route::get('payments', [AdminController::class, 'payments']);
            Route::patch('payments/{payment}', [AdminController::class, 'reviewPayment']);
            Route::get('audit-logs', [AuditLogController::class, 'index']);
            Route::get('users', [AdminUserController::class, 'index']);
            Route::post('users/{user}/lock', [AdminUserController::class, 'lock']);
            Route::post('users/{user}/unlock', [AdminUserController::class, 'unlock']);
            // D13/D14: suspension, remediation review and read-only cross-organization monitoring.
            Route::get('venues/{venueId}/suspensions/preview', [AdminVenueSuspensionController::class, 'preview']);
            Route::post('venues/{venueId}/suspensions', [AdminVenueSuspensionController::class, 'store']);
            Route::get('venue-remediations', [AdminVenueRemediationController::class, 'index']);
            Route::get('venue-remediations/{id}', [AdminVenueRemediationController::class, 'show']);
            Route::post('venue-remediations/{id}/review', [AdminVenueRemediationController::class, 'review']);
            Route::get('monitoring/venues', [AdminVenueMonitoringController::class, 'index']);
            Route::get('monitoring/venues/{venueId}', [AdminVenueMonitoringController::class, 'show']);
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
        });

        // BR-23: organization operations need an owner or staff membership; platform admin rights do not stand in.
        Route::middleware(['venue_admin'])->group(function () {
            Route::get('venues', [AdminController::class, 'venues']);
            Route::get('courts', [CourtController::class, 'adminIndex']);
            Route::apiResource('courts', CourtController::class)->except(['index', 'show', 'destroy']);
            Route::post('courts/{court}/open', [CourtLifecycleController::class, 'open']);
            Route::post('courts/{court}/pause', [CourtLifecycleController::class, 'pause']);
            Route::post('courts/{court}/resume', [CourtLifecycleController::class, 'resume']);
            Route::post('courts/{court}/close', [CourtLifecycleController::class, 'close']);
            Route::post('courts/{court}/pause/preview', [CourtLifecycleController::class, 'previewPause']);
            Route::post('courts/{court}/close/preview', [CourtLifecycleController::class, 'previewClose']);
            Route::get('contracts', [AdminContractController::class, 'index']);
            Route::get('contracts/{id}', [AdminContractController::class, 'show']);
            Route::post('contracts/{id}/cancel', [AdminContractController::class, 'cancel']);
            Route::get('contracts.csv', [AdminContractController::class, 'exportCsv']);
            Route::post('upload', [AdminController::class, 'upload']);
        });
    });
});

// Tenant-scoped routes: ResolveTenantContext binds the request to an organization.
Route::middleware([ResolveTenantContext::class])->group(function () {
    Route::post('webhooks/{provider}', ProviderWebhookController::class)->withoutMiddleware('throttle:api');
    Route::middleware(['auth:sanctum', 'not_restricted'])->group(function () {
        Route::get('capabilities', [OrganizationController::class, 'capabilities']);
        Route::middleware(RequireOrganizationRole::class.':organization_owner,staff')->group(function () {
            // POS customer search; FR-110 customer insights are per venue below.
            Route::get('customers', [CustomerController::class, 'index']);
        });

        // Reservation commands are the canonical write path. Each command
        // controller delegates to IdempotencyService and persists its outcome.
        Route::get('reservations', [ReservationController::class, 'index']);
        Route::get('me/reservations', MyReservationController::class);
        Route::get('me/venue-credits', [VenueCreditController::class, 'index']);
        Route::post('me/venue-credits/{organization}/cash-out-codes', [VenueCreditController::class, 'storeCashOutCode'])->middleware('verified');
        Route::get('admin/reservations', AdminReservationController::class)->middleware('venue_admin');
        Route::get('admin/affected-reservations', [AffectedReservationCaseController::class, 'index'])->middleware('venue_admin');
        Route::patch('admin/affected-reservations/{case}', [AffectedReservationCaseController::class, 'update'])->middleware('venue_admin');
        Route::post('price-quotes', [ReservationController::class, 'quote']);
        Route::post('reservations', [ReservationController::class, 'store'])->middleware('verified');
        Route::post('reservations/checkout', [ReservationController::class, 'checkout'])->middleware('verified');
        Route::get('reservations/{reservation}', [ReservationController::class, 'show']);
        Route::get('reservations/{reservation}/payment', [ReservationController::class, 'payment']);
        Route::post('reservations/{reservation}/payment', [ReservationController::class, 'createPayment'])->middleware('verified');
        Route::post('reservations/{reservation}/confirm', [ReservationController::class, 'confirm'])->middleware('verified');
        Route::post('reservations/{reservation}/cancel-payment', CancelReservationPaymentController::class);
        Route::post('loyalty/redeem', [LoyaltyController::class, 'redeem'])->middleware('verified');

        // Module 06 D10, BR-134: only the owner corrects an operational record and reads its history.
        Route::middleware(RequireOrganizationRole::class.':organization_owner')->group(function () {
            Route::post('attendance/adjustments', [AttendanceController::class, 'adjust']);
            Route::get('attendance/{code}/adjustments', [AttendanceController::class, 'adjustments']);
        });

        Route::middleware(RequireOrganizationRole::class.':organization_owner,staff')->group(function () {
            Route::post('attendance/check-in', [AttendanceController::class, 'checkIn']);
            Route::post('attendance/check-out', [AttendanceController::class, 'checkOut']);
            Route::post('attendance/no-show', [AttendanceController::class, 'markNoShow']);
            // Design D8: operator commands on a booking with an open affected-booking case; reassign-court is retired.
            Route::post('reservations/{reservation}/move-court', [AffectedBookingCommandController::class, 'moveCourt']);
            Route::post('reservations/{reservation}/change-time', [AffectedBookingCommandController::class, 'changeTime']);
            Route::post('reservations/{reservation}/operator-cancel', [AffectedBookingCommandController::class, 'cancel']);
            Route::get('attendance/{code}', [AttendanceController::class, 'show']);
            Route::get('operations/venues/{venue}/day', [OperationsController::class, 'day']);
            Route::get('operations/venues/{venue}/in-use', [OperationsController::class, 'inUse']);
            Route::get('operations/venues/{venue}/customers', [VenueCustomerController::class, 'index']);
            Route::get('operations/venues/{venue}/customers/{user}', [VenueCustomerController::class, 'show']);

            Route::get('service-tabs', [ServiceTabController::class, 'index']);
            Route::post('service-tabs', [ServiceTabController::class, 'store']);
            Route::get('service-tabs/{reservation}', [ServiceTabController::class, 'show']);
            Route::delete('service-tabs/{reservation}/items/{item}', [ServiceTabController::class, 'removeItem']);
            Route::post('service-tabs/{tab}/settle', [ServiceTabController::class, 'settle']);
            Route::post('service-tabs/{tab}/cancel', [ServiceTabController::class, 'cancel']);
        });

        Route::get('recurring-series', [AdvancedBookingController::class, 'series']);
        Route::post('recurring-series', [AdvancedBookingController::class, 'createSeries'])->middleware('verified');
        Route::post('recurring-series/preview', [AdvancedBookingController::class, 'previewSeries'])->middleware('verified');
        Route::get('monthly-invoices/{invoice}', [AdvancedBookingController::class, 'invoice']);
        Route::middleware('feature:marketplace_split_payments')->group(function () {
            Route::post('reservations/{reservation}/split', [AdvancedBookingController::class, 'createSplit']);
            Route::get('split-groups/{split}', [AdvancedBookingController::class, 'split']);
            Route::post('split-shares/{share}/pay', [AdvancedBookingController::class, 'paySplitShare']);
            Route::post('split-groups/{split}/cover-shortfall', [AdvancedBookingController::class, 'coverSplit']);
        });

        Route::middleware('feature:marketplace_slot_resale')->group(function () {
            Route::get('marketplace/listings', [AdvancedBookingController::class, 'listings']);
            Route::post('marketplace/entitlements/{entitlement}/list', [ResaleListingController::class, 'store']);
            Route::get('marketplace/listings/{listing}', [ResaleListingController::class, 'show']);
            Route::post('marketplace/listings/{listing}/checkout', [ResaleListingController::class, 'lock'])->middleware('verified');
            Route::post('marketplace/listings/{listing}/release', [ResaleListingController::class, 'release']);
            Route::post('marketplace/listings/{listing}/withdraw', [AdvancedBookingController::class, 'withdrawListing']);
        });

        Route::middleware('feature:tenant_pos_and_wallet')->group(function () {
            Route::get('pos/products', [PosController::class, 'products']);
            Route::get('pos/products/{product}', [PosController::class, 'showProduct']);
            Route::post('pos/products', [PosController::class, 'storeProduct'])->middleware(RequireOrganizationRole::class.':organization_owner');
            Route::put('pos/products/{product}', [PosController::class, 'updateProduct'])->middleware(RequireOrganizationRole::class.':organization_owner');
            Route::delete('pos/products/{product}', [PosController::class, 'destroyProduct'])->middleware(RequireOrganizationRole::class.':organization_owner');
            Route::post('pos/products/{product}/stock', [PosController::class, 'adjustStock'])->middleware(RequireOrganizationRole::class.':organization_owner');
            Route::get('pos/inventory/movements', [PosController::class, 'movements'])->middleware(RequireOrganizationRole::class.':organization_owner,staff');
            Route::get('pos/inventory/kpis', [PosController::class, 'inventoryKpis'])->middleware(RequireOrganizationRole::class.':organization_owner,staff');
            Route::get('pos/inventory/transfers', [StockTransferController::class, 'index'])->middleware(RequireOrganizationRole::class.':organization_owner,staff');
            Route::post('pos/inventory/transfers', [StockTransferController::class, 'store'])->middleware(RequireOrganizationRole::class.':organization_owner');
            Route::post('pos/quote', [PosController::class, 'quote'])->middleware(RequireOrganizationRole::class.':organization_owner,staff');
            Route::post('pos/orders', [PosController::class, 'completeOrder'])->middleware(RequireOrganizationRole::class.':organization_owner,staff');
            Route::get('pos/orders/{order}', [PosController::class, 'showOrder'])->middleware(RequireOrganizationRole::class.':organization_owner,staff');
            Route::post('pos/orders/{order}/cancel-qr', [PosController::class, 'cancelQrPayment'])->middleware(RequireOrganizationRole::class.':organization_owner,staff');
            Route::post('pos/orders/{order}/receipt/print', [PosController::class, 'printReceipt'])->middleware(RequireOrganizationRole::class.':organization_owner,staff');
            Route::post('pos/orders/{order}/receipt/send', [PosController::class, 'sendReceipt'])->middleware(RequireOrganizationRole::class.':organization_owner,staff');
            Route::post('pos/orders/{order}/refund', [PosController::class, 'refundOrder'])->middleware(RequireOrganizationRole::class.':organization_owner');
            Route::get('pos/shifts', [PosController::class, 'shifts'])->middleware(RequireOrganizationRole::class.':organization_owner,staff');
            Route::get('pos/shifts/active', [PosController::class, 'activeShift'])->middleware(RequireOrganizationRole::class.':organization_owner,staff');
            Route::post('pos/shifts', [PosController::class, 'openShift'])->middleware(RequireOrganizationRole::class.':organization_owner,staff');
            Route::post('pos/shifts/{shift}/close', [PosController::class, 'closeShift'])->middleware(RequireOrganizationRole::class.':organization_owner,staff');
            Route::post('pos/shifts/{shift}/cash-movement', [PosController::class, 'recordCashMovement'])->middleware(RequireOrganizationRole::class.':organization_owner,staff');
            Route::get('pos/venue-credits/customers/{user}', [VenueCreditCashOutController::class, 'customer'])->middleware(RequireOrganizationRole::class.':organization_owner,staff');
            Route::post('pos/shifts/{shift}/venue-credit-cash-outs/lookup', [VenueCreditCashOutController::class, 'lookup'])->middleware([RequireOrganizationRole::class.':organization_owner,staff', 'throttle:venue-credit-lookup']);
            Route::post('pos/shifts/{shift}/venue-credit-cash-outs', [VenueCreditCashOutController::class, 'store'])->middleware(RequireOrganizationRole::class.':organization_owner,staff');
            Route::post('pos/shifts/{shift}/review', [PosController::class, 'reviewReconciliation'])->middleware(RequireOrganizationRole::class.':organization_owner');
            Route::get('memberships/plans', [PosController::class, 'membershipPlans']);
            Route::get('memberships/benefits', [PosController::class, 'benefits']);
            Route::post('memberships/plans', [PosController::class, 'publishMembershipPlan'])->middleware(RequireOrganizationRole::class.':organization_owner');
            Route::post('memberships', [PosController::class, 'assignMembership'])->middleware(RequireOrganizationRole::class.':organization_owner');
            Route::post('loyalty/rules', [PosController::class, 'publishLoyaltyRule'])->middleware(RequireOrganizationRole::class.':organization_owner');
            Route::post('wallet/credit', [PosController::class, 'creditWallet'])->middleware(RequireOrganizationRole::class.':organization_owner');
            Route::post('wallet/spend', [PosController::class, 'spendWallet']);
        });

        // A direct-owner payment interface, so it follows direct_owner_payments rather than the payouts group below.
        Route::get('owner-payment-exceptions', [OwnerPaymentExceptionController::class, 'index'])
            ->middleware([RequireOrganizationRole::class.':organization_owner', 'feature:direct_owner_payments']);
        Route::get('owner-payment-exceptions/{exception}', [OwnerPaymentExceptionController::class, 'show'])
            ->whereNumber('exception')
            ->middleware([RequireOrganizationRole::class.':organization_owner', 'feature:direct_owner_payments']);
        Route::get('owner-venue-credits', [OwnerVenueCreditController::class, 'index'])
            ->middleware([RequireOrganizationRole::class.':organization_owner', 'feature:direct_owner_payments']);

        Route::middleware([RequireOrganizationRole::class.':organization_owner', 'feature:marketplace_payouts'])->group(function () {
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
        });

        // Host KYC is cross-tenant PII and partner approval: platform admin only.
        Route::middleware(['admin'])->group(function () {
            Route::get('admin/kyc', [AdminKycController::class, 'index']);
            Route::get('admin/kyc/stats', [AdminKycController::class, 'stats']);
            Route::get('admin/kyc/{id}', [AdminKycController::class, 'show']);
            Route::post('admin/kyc/{id}/review', [AdminKycController::class, 'review']);
        });

        // Dispute arbitration is a platform function the owner shares for their own organization.
        Route::middleware([RequireOrganizationRole::class.':organization_owner,platform_admin'])->group(function () {
            Route::get('admin/disputes', [AdminDisputeController::class, 'index']);
            Route::get('admin/disputes/stats', [AdminDisputeController::class, 'stats']);
            Route::get('admin/disputes/{id}', [AdminDisputeController::class, 'show']);
            Route::post('admin/disputes/{id}/arbitrate', [AdminDisputeController::class, 'arbitrate']);
            Route::post('admin/disputes/{id}/reassign', [AdminDisputeController::class, 'reassign']);
            Route::post('admin/disputes/{id}/close', [AdminDisputeController::class, 'close']);
            Route::post('admin/disputes/{id}/reopen', [AdminDisputeController::class, 'reopen']);
        });

        Route::middleware([RequireOrganizationRole::class.':organization_owner'])->group(function () {
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

        // D25: price tables, their court links, price coverage and promotion programs belong to the owner; demo owners only simulate changes.
        Route::middleware([RequireOrganizationRole::class.':organization_owner', SimulateDemoMutations::class])->prefix('admin')->group(function () {
            Route::get('venues/{venue}/price-tables', [PriceTableController::class, 'index']);
            Route::post('venues/{venue}/price-tables', [PriceTableController::class, 'store']);
            Route::get('price-tables/{table}', [PriceTableController::class, 'show']);
            Route::patch('price-tables/{table}', [PriceTableController::class, 'update']);
            Route::post('price-tables/{table}/versions', [PriceTableController::class, 'publishVersion']);
            Route::post('price-tables/{table}/links', [PriceTableController::class, 'applyLinks']);
            Route::post('price-tables/{table}/retire', [PriceTableController::class, 'retire']);
            Route::post('courts/{court}/price-table-links/end', [CourtPricingController::class, 'endLink']);
            Route::get('courts/{court}/price-coverage', [CourtPricingController::class, 'coverage']);
            // Promotion programs, their codes and usage (D7, D8, D14, FR-68); programs are never deleted.
            Route::get('venues/{venue}/promotion-programs', [PromotionProgramController::class, 'index']);
            Route::post('venues/{venue}/promotion-programs', [PromotionProgramController::class, 'store']);
            Route::get('promotion-programs/{program}', [PromotionProgramController::class, 'show']);
            Route::patch('promotion-programs/{program}', [PromotionProgramController::class, 'update']);
            Route::post('promotion-programs/{program}/stop', [PromotionProgramController::class, 'stop']);
            Route::get('promotion-programs/{program}/codes', [PromotionProgramController::class, 'codes']);
            Route::get('promotion-programs/{program}/usage', [PromotionProgramController::class, 'usage']);
        });
    });

});
