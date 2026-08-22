<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CourtController;
use App\Http\Controllers\LegacyBookingAdapterController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SepayWebhookController;
use App\Http\Controllers\V2PriceRuleController;
use App\Http\Controllers\V2ReservationController;
use App\Http\Controllers\WaitlistController;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SimulateDemoMutations;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('courts', [CourtController::class, 'index']);
    Route::get('courts/{court}/reviews', [ReviewController::class, 'index']);
    Route::get('venues/{slug}', [AdminController::class, 'showVenue']);
    Route::get('availability', AvailabilityController::class);
    Route::post('webhooks/sepay', SepayWebhookController::class)->withoutMiddleware('throttle:api');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', fn (Request $request) => $request->user());
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/email/verification-notification', function (Request $request) {
            if (! $request->user()->hasVerifiedEmail()) {
                $request->user()->sendEmailVerificationNotification();
            }

            return ['message' => 'Đã gửi email xác thực.'];
        })->middleware('throttle:6,1');
        Route::get('auth/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
            $request->fulfill();

            return ['message' => 'Email đã được xác thực.'];
        })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
        Route::patch('profile', fn (Request $request) => $request->user()->update($request->validate(['name' => 'sometimes|string|max:120'])) ? $request->user()->fresh() : $request->user());
        Route::patch('profile/password', [AuthController::class, 'changePassword']);
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
        Route::get('bookings/{booking:code}/calendar.ics', [BookingController::class, 'calendar']);
        Route::post('bookings/{booking:code}/demo-pay', [BookingController::class, 'demoPay']);
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
            Route::get('courts', [CourtController::class, 'adminIndex']);
            Route::apiResource('courts', CourtController::class)->except(['index', 'show']);
            Route::get('bookings', [AdminController::class, 'bookings']);
            Route::patch('bookings/{booking}/no-show', [AdminController::class, 'noShow']);
            Route::get('payments', [AdminController::class, 'payments']);
            Route::patch('payments/{payment}', [AdminController::class, 'reviewPayment']);
            Route::post('courts/{court}/prices', [AdminController::class, 'prices']);
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
        });
    });
});

// v2 API Route Group with Tenant Isolation Middleware
Route::prefix('v2')->middleware([ResolveTenantContext::class])->group(function () {
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
    });
});
