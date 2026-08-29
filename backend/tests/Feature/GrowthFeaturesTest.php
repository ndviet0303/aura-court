<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Notifications\AuraBookingNotification;
use App\Services\Booking\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GrowthFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_and_membership_discounts_are_applied_to_booking(): void
    {
        $user = User::factory()->create();
        $court = Court::create(['name' => 'Sân Ưu Đãi', 'is_active' => true]);
        $date = now()->addDays(2)->setTime(10, 0);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 200000, 'is_active' => true]);
        $plan = MembershipPlan::create(['name' => 'Gold', 'discount_percent' => 10, 'is_active' => true]);
        Membership::create(['user_id' => $user->id, 'membership_plan_id' => $plan->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(), 'status' => 'active']);
        $coupon = Coupon::create(['code' => 'WELCOME10', 'discount_type' => 'percent', 'discount_value' => 10, 'usage_limit' => 5, 'is_active' => true]);

        $booking = app(BookingService::class)->create($user->id, $court->id, [$date->toIso8601String()], 'welcome10');

        $this->assertSame(160000, $booking->total_amount);
        $this->assertSame(40000, $booking->discount_amount);
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'usage_count' => 1]);
    }

    public function test_cancelled_slot_notifies_waitlist(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $waiting = User::factory()->create();
        $court = Court::create(['name' => 'Sân Danh Sách Chờ', 'is_active' => true]);
        $date = now()->addDays(2)->setTime(10, 0);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);
        $booking = app(BookingService::class)->create($owner->id, $court->id, [$date->toIso8601String()]);

        $this->actingAs($waiting)->postJson('/api/v1/waitlist', ['court_id' => $court->id, 'starts_at' => $date->toIso8601String()])->assertCreated();
        $this->actingAs($owner)->postJson('/api/v1/bookings/'.$booking->code.'/cancel')->assertOk();

        $this->assertDatabaseHas('waitlist_entries', ['user_id' => $waiting->id, 'status' => 'notified']);
        Notification::assertSentTo($waiting, AuraBookingNotification::class, fn (AuraBookingNotification $notification) => $notification->event === 'waitlist_available');
    }

    public function test_completed_booking_can_be_reviewed_and_calendar_downloaded(): void
    {
        $user = User::factory()->create();
        $court = Court::create(['name' => 'Sân Đánh Giá', 'is_active' => true]);
        $start = Carbon::now()->subDays(2)->setTime(10, 0);
        $booking = Booking::create(['code' => 'BKREVIEW001', 'user_id' => $user->id, 'court_id' => $court->id, 'total_amount' => 120000, 'status' => 'confirmed']);
        $booking->slots()->create(['court_id' => $court->id, 'starts_at' => $start, 'ends_at' => $start->copy()->addHour(), 'unit_price' => 120000, 'status' => 'confirmed']);

        $this->actingAs($user)->postJson('/api/v1/reviews', ['booking_code' => $booking->code, 'rating' => 5, 'body' => 'Rất tốt'])->assertCreated();
        $this->assertDatabaseHas('reviews', ['booking_id' => $booking->id, 'rating' => 5]);
        $this->actingAs($user)->get('/api/v1/bookings/'.$booking->code.'/calendar.ics')->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=UTF-8')->assertSee('BEGIN:VCALENDAR');
    }

    public function test_notification_center_can_mark_notifications_read(): void
    {
        $user = User::factory()->create();
        $user->notify(new AuraBookingNotification('test', 'Thông báo test', 'Nội dung test', '/booking'));

        $response = $this->actingAs($user)->getJson('/api/v1/notifications')->assertOk();
        $id = $response->json('notifications.data.0.id');
        $this->actingAs($user)->postJson('/api/v1/notifications/'.$id.'/read')->assertOk();
        $this->assertDatabaseMissing('notifications', ['id' => $id, 'read_at' => null]);
    }

    public function test_expiring_booking_receives_one_reminder(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $court = Court::create(['name' => 'Sân Nhắc Thanh Toán', 'is_active' => true]);
        $booking = Booking::create(['code' => 'BKEXPIRING01', 'user_id' => $user->id, 'court_id' => $court->id, 'total_amount' => 100000, 'status' => 'pending_payment', 'payment_expires_at' => now()->addMinutes(4)]);

        $this->assertSame(1, app(BookingService::class)->notifyExpiring());
        $this->assertNotNull($booking->fresh()->payment_expiring_notified_at);
        Notification::assertSentTo($user, AuraBookingNotification::class, fn (AuraBookingNotification $notification) => $notification->event === 'booking_expiring');
        $this->assertSame(0, app(BookingService::class)->notifyExpiring());
    }
}
