<?php

namespace Tests\Concerns;

use App\Models\Court;
use App\Models\OwnerPaymentAttempt;
use App\Models\PromotionProgram;
use App\Models\ReceivingConnection;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Services\Infrastructure\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Direct-owner bookings for tests (module 04): the venue collects through a verified SePay connection, a customer
 * checks out one interval, and the provider's webhook reports a transfer. No provider is called; the webhook is
 * signed with the connection's own test secret.
 *
 *     $court = $this->payableCourt();                          // bookableCourt + connection + policy, flag on
 *     $res = $this->checkout($this->customer(), $court, '2026-11-03 15:30', '2026-11-03 17:00', 150000);
 *     $this->transfer($court, $res->json('reservation.code'), 150000);
 */
trait BooksCourts
{
    use BuildsVenues;

    protected const WEBHOOK_SECRET = 'test-webhook-secret';

    protected const ACCOUNT_NUMBER = '0123456789';

    /** Each HTTP request starts without a tenant, as under php-fpm; the singleton would otherwise leak across requests. */
    protected function setUpBooksCourts(): void
    {
        Event::listen(RequestHandled::class, fn () => $this->app->forgetInstance(TenantContext::class));
    }

    protected function enableDirectPayments(): void
    {
        config(['features.flags.direct_owner_payments' => true, 'features.pilot_organizations' => ['*']]);
    }

    /** A bookable court whose venue collects payments through a verified connection; direct payments are on. */
    protected function payableCourt(int $pricePerHour = 100000): Court
    {
        $this->enableDirectPayments();
        $court = $this->bookableCourt($pricePerHour);
        $this->connectVenue($court->venue);

        return $court;
    }

    protected function connectVenue(Venue $venue): ReceivingConnection
    {
        $connection = ReceivingConnection::create([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->id,
            'owner_user_id' => $this->ownerOf($venue->organization)->id,
            'provider' => 'sepay',
            'routing_id' => (string) Str::uuid(),
            'bank_code' => 'VCB',
            'account_name_ciphertext' => Crypt::encryptString('CONG TY SAN'),
            'account_number_ciphertext' => Crypt::encryptString(self::ACCOUNT_NUMBER),
            'account_last_four' => substr(self::ACCOUNT_NUMBER, -4),
            'status' => ReceivingConnection::STATUS_VERIFIED,
            'webhook_secret_ciphertext' => Crypt::encryptString(self::WEBHOOK_SECRET),
            'credential_version' => 1,
            'owner_attested_at' => now()->subDay(),
            'verified_at' => now()->subDay(),
            'effective_from' => now()->subDay(),
        ]);
        $venue->forceFill(['current_receiving_connection_id' => $connection->id])->save();
        VenueBookingPolicy::create(['venue_id' => $venue->id, 'version' => 1, 'settings' => ['hold_ttl_minutes' => 30], 'effective_at' => now()->subDay()]);

        return $connection;
    }

    /** @param  array<string, mixed>  $attributes */
    protected function customer(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /** @param  array<string, mixed>  $extra */
    protected function checkout(User $user, Court $court, string $startsAt, string $endsAt, ?int $expectedTotal, array $extra = []): TestResponse
    {
        $body = ['court_id' => $court->id, 'starts_at' => $startsAt, 'ends_at' => $endsAt] + $extra;
        if ($expectedTotal !== null) {
            $body['expected_total'] = $expectedTotal;
        }

        return $this->command($user, '/api/reservations/checkout', $body);
    }

    /**
     * A state-changing command as $user, with its own Idempotency-Key.
     *
     * @param  array<string, mixed>  $body
     */
    protected function command(User $user, string $uri, array $body = []): TestResponse
    {
        return $this->actingAs($user)->postJson($uri, $body, ['Idempotency-Key' => (string) Str::uuid()]);
    }

    /** The provider reports an inbound transfer to the court's venue connection; the receipt is processed at once. */
    protected function transfer(Court $court, string $code, int $amount, ?CarbonInterface $at = null, ?string $eventId = null): TestResponse
    {
        $connection = ReceivingConnection::query()->findOrFail($court->venue()->value('current_receiving_connection_id'));
        $eventId ??= (string) Str::uuid();
        $payload = [
            'id' => $eventId,
            'transactionDate' => ($at ?? now())->format('Y-m-d H:i:s'),
            'accountNumber' => self::ACCOUNT_NUMBER,
            'code' => $code,
            'transferType' => 'in',
            'transferAmount' => $amount,
            'referenceCode' => 'FT'.substr(md5($eventId), 0, 10),
        ];

        return $this->call('POST', "/api/v1/webhooks/sepay/connections/{$connection->routing_id}", [], [], [], [
            'HTTP_AUTHORIZATION' => 'Apikey '.self::WEBHOOK_SECRET,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * A public program of the court's venue covering the court, running from a day ago until $endsAt.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function publicVoucher(Court $court, CarbonInterface $endsAt, array $attributes = []): PromotionProgram
    {
        $program = PromotionProgram::create($attributes + [
            'venue_id' => $court->venue_id,
            'name' => 'Giảm 20.000',
            'type' => PromotionProgram::TYPE_PUBLIC,
            'discount_kind' => PromotionProgram::KIND_FIXED,
            'amount' => 20000,
            'starts_at' => now()->subDay(),
            'ends_at' => $endsAt,
            'max_uses' => 10,
        ]);
        $program->courts()->attach($court->id);

        return $program;
    }

    /** The payment code of a booking's latest payment request. */
    protected function paymentCode(string $reservationCode): string
    {
        return (string) OwnerPaymentAttempt::query()->where('purpose_id', $reservationCode)->latest('id')->value('reference');
    }
}
