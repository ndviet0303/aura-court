<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CourtController;
use App\Http\Controllers\SepayWebhookController;
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
        Route::post('bookings/{booking:code}/cancel', [BookingController::class, 'cancel']);
        Route::post('bookings/{booking:code}/cancel-slots', [BookingController::class, 'cancelSlots']);
        Route::post('bookings/{booking:code}/reschedule', [BookingController::class, 'reschedule']);
        Route::get('bookings/{booking:code}/payment-status', [BookingController::class, 'paymentStatus']);
        Route::get('bookings/{booking:code}/payment', [BookingController::class, 'payment']);
        Route::post('bookings/{booking:code}/demo-pay', [BookingController::class, 'demoPay']);

        Route::prefix('admin')->middleware(['admin', SimulateDemoMutations::class])->group(function () {
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
        });
    });
});
