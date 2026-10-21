<?php

namespace Tests\Concerns;

use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceTable;
use App\Models\ReceivingConnection;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/** A direct-owner venue with one priced court and a verified receiver, and checkout requests against it. */
trait BooksDirectOwnerCourt
{
    use PaysOwnerByQr;
    use PricesCourts;

    /**
     * @param  array<string, mixed>  $booking  overrides of the venue policy's booking settings
     * @return array{organization: Organization, owner: User, payer: User, venue: Venue, court: Court, table: PriceTable, connection: ReceivingConnection, secret: string}
     */
    protected function directOwnerCourt(int $price = 200000, array $booking = []): array
    {
        $organization = Organization::create([
            'name' => 'Intake Club',
            'slug' => 'intake-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'partner_status' => 'approved',
            'settings' => ['timezone' => 'Asia/Ho_Chi_Minh'],
        ]);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $payer = $this->bookingCustomer($organization);
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);
        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => 'Intake Venue',
            'slug' => 'intake-venue-'.Str::lower(Str::random(8)),
            'address' => '1 Intake Street',
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Intake Court', 'is_active' => true]);
        VenueBookingPolicy::create([
            'venue_id' => $venue->id,
            'version' => 1,
            'created_by' => $owner->id,
            'effective_at' => now()->subDay(),
            'settings' => [
                'booking' => [...['hold_ttl_minutes' => 30, 'max_days_ahead' => 60, 'min_duration_minutes' => 60, 'max_duration_minutes' => 240], ...$booking],
                'arrival' => ['early_minutes' => 15, 'late_grace_minutes' => 10, 'no_show_after_minutes' => 30],
                'recurring' => ['payment_policy' => 'per_period', 'conflict_policy' => 'all_or_nothing', 'overdue_release_hours' => 24],
                'transfer' => ['cutoff_hours' => 2, 'max_consideration_percent' => 100, 'free_transfer_allowed' => false, 'seller_reimbursement_required' => true],
            ],
        ]);
        $table = $this->priceCourt($court, $price);
        [$connection, $secret] = $this->connectOwnerReceiver($venue);
        config([
            'features.flags.direct_owner_payments' => true,
            'features.pilot_organizations' => [(string) $organization->id],
        ]);

        return compact('organization', 'owner', 'payer', 'venue', 'court', 'table', 'connection', 'secret');
    }

    protected function bookingCustomer(Organization $organization, array $attributes = []): User
    {
        $customer = User::factory()->create(['email_verified_at' => now(), ...$attributes]);
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $customer->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);

        return $customer;
    }

    /** @param  array<int, CarbonInterface>  $starts */
    protected function quoteFor(User $customer, Organization $organization, Court $court, array $starts, array $extra = []): TestResponse
    {
        return $this->actingAs($customer)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/price-quotes', [
            'court_id' => $court->id,
            'slots' => array_map(fn (CarbonInterface $start): string => $start->toIso8601String(), $starts),
            ...$extra,
        ]);
    }

    /**
     * Quotes the given slots and checks them out for the amount the quote showed.
     *
     * @param  array<int, CarbonInterface>  $starts
     */
    protected function checkoutFor(User $customer, Organization $organization, Court $court, array $starts, array $extra = []): TestResponse
    {
        $quote = $this->quoteFor($customer, $organization, $court, $starts, $extra)->assertCreated()->json('quote');

        return $this->submitCheckout($customer, $organization, ['quote_id' => $quote['id'], 'expected_total' => (int) $quote['total_amount']]);
    }

    protected function submitCheckout(User $customer, Organization $organization, array $payload): TestResponse
    {
        return $this->actingAs($customer)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson('/api/reservations/checkout', $payload);
    }
}
