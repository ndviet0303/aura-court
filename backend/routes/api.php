<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CourtController;
use App\Http\Controllers\SepayWebhookController;
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
        Route::patch('profile', fn (Request $request) => $request->user()->update($request->validate(['name' => 'sometimes|string|max:120'])) ? $request->user()->fresh() : $request->user());
        Route::get('bookings', [BookingController::class, 'index']);
        Route::post('bookings', [BookingController::class, 'store']);
        Route::get('bookings/{booking:code}', [BookingController::class, 'show']);
        Route::post('bookings/{booking:code}/cancel', [BookingController::class, 'cancel']);
        Route::get('bookings/{booking:code}/payment-status', [BookingController::class, 'paymentStatus']);

        Route::prefix('admin')->middleware('admin')->group(function () {
            Route::apiResource('courts', CourtController::class)->except(['index', 'show']);
            Route::get('bookings', [AdminController::class, 'bookings']);
            Route::post('courts/{court}/prices', [AdminController::class, 'prices']);
            Route::post('courts/{court}/blocks', [AdminController::class, 'blocks']);
            Route::get('refunds', [AdminController::class, 'refunds']);
            Route::patch('refunds/{refund}', [AdminController::class, 'processRefund']);
            Route::get('reports', [AdminController::class, 'report']);
        });
    });
});
