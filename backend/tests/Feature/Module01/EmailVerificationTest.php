<?php

namespace Tests\Feature\Module01;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsVenues;
use Tests\TestCase;

/** FR-ACC-04, FR-ACC-05, BR-ACC-06, BR-ACC-11: verification links, resend, expiry, and the booking gate. */
class EmailVerificationTest extends TestCase
{
    use BuildsVenues;
    use RefreshDatabase;

    /** The link a verification mail would carry; building it issues a new one-time token. */
    private function verificationLink(User $user): string
    {
        return (new VerifyEmailNotification)->toMail($user)->viewData['url'];
    }

    public function test_fr_acc_04_a_valid_link_verifies_the_email_once(): void
    {
        $user = User::factory()->unverified()->create();
        $link = $this->verificationLink($user);

        $this->getJson($link)->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $this->getJson($link)->assertForbidden();
    }

    public function test_br_acc_11_an_expired_link_does_not_verify(): void
    {
        $user = User::factory()->unverified()->create();
        $link = $this->verificationLink($user);

        $this->travel(16)->minutes();

        $this->getJson($link)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_br_acc_11_a_tampered_link_does_not_verify(): void
    {
        $user = User::factory()->unverified()->create();
        $link = $this->verificationLink($user);

        $this->getJson(str_replace('/'.$user->id.'/', '/'.($user->id + 1).'/', $link))->assertNotFound();
        $this->getJson(preg_replace('/signature=[^&]+/', 'signature=bad', $link))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_fr_acc_05_resend_issues_a_new_link_and_retires_the_old_one(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $old = $this->verificationLink($user);
        $this->travel(16)->minutes();

        $this->actingAs($user)->postJson('/api/auth/email/verification-notification')->assertOk();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
        $new = $this->verificationLink($user);

        $this->getJson($old)->assertForbidden();
        $this->getJson($new)->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_fr_acc_05_resend_is_throttled_right_after_registration(): void
    {
        Notification::fake();
        $this->postJson('/api/auth/register', [
            'name' => 'Nguyễn Văn A', 'email' => 'a@example.com',
            'password' => 'Secret123', 'password_confirmation' => 'Secret123',
        ])->assertCreated();

        $this->postJson('/api/auth/email/verification-notification')->assertTooManyRequests();
        $this->travel(61)->seconds();
        $this->postJson('/api/auth/email/verification-notification')->assertOk();
    }

    public function test_alt_07_an_already_verified_email_is_not_sent_again(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/auth/email/verification-notification')
            ->assertOk()->assertJsonPath('message', 'Email đã được xác thực trước đó.');

        Notification::assertNothingSent();
    }

    public function test_br_acc_06_an_unverified_customer_cannot_create_a_booking(): void
    {
        $court = $this->bookableCourt();
        $start = now('Asia/Ho_Chi_Minh')->addDay()->setTime(8, 0);
        $payload = ['court_id' => $court->id, 'start_time' => $start->toIso8601String(), 'end_time' => $start->copy()->addHour()->toIso8601String()];
        $unverified = User::factory()->unverified()->create();

        foreach (['/api/reservations', '/api/reservations/checkout', '/api/recurring-series'] as $uri) {
            $this->actingAs($unverified)->postJson($uri, $payload)
                ->assertForbidden()->assertJsonPath('message', 'Your email address is not verified.');
        }
        $this->assertDatabaseCount('reservations', 0);

        // A verified account passes the gate (whatever the booking validation then says).
        $response = $this->actingAs(User::factory()->create())->postJson('/api/reservations/checkout', $payload);
        $this->assertNotSame('Your email address is not verified.', $response->json('message'));
    }
}
