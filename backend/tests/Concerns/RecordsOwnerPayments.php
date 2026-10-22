<?php

namespace Tests\Concerns;

use App\Models\BookingAttendance;
use App\Models\Court;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Module 05 owner finance: bookings, their attendance and the transfers the owner received, written straight to the
 * tables modules 03/04 fill. No provider is called and the snapshots are dummy ciphertexts.
 *
 *     $booking = $this->booking($court, 200000, confirmedAt: $at);
 *     $this->attendance($booking, BookingAttendance::STATUS_CHECKED_OUT, $in, $out);
 *     $this->receipt($court->venue, 200000, $at, $booking);            // allocated to the booking
 *     $this->receipt($court->venue, 50000, $at, status: 'unmatched'); // names no booking
 */
trait RecordsOwnerPayments
{
    /** @param  array<string, mixed>  $attributes */
    protected function booking(Court $court, int $total, ?CarbonInterface $confirmedAt = null, string $status = 'confirmed', array $attributes = []): Reservation
    {
        return Reservation::query()->withoutGlobalScopes()->create($attributes + [
            'organization_id' => $court->venue->organization_id,
            'user_id' => User::factory()->create()->id,
            'court_id' => $court->id,
            'code' => 'BK'.Str::upper(Str::random(8)),
            'status' => $status,
            'total_amount' => $total,
            'confirmed_at' => $confirmedAt === null ? null : $this->wallTime($confirmedAt),
        ]);
    }

    protected function attendance(Reservation $booking, string $status, CarbonInterface $checkedInAt, ?CarbonInterface $checkedOutAt = null): void
    {
        DB::table('booking_attendances')->insert([
            'organization_id' => $booking->organization_id,
            'reservation_id' => $booking->id,
            'status' => $status,
            'checked_in_at' => $this->wallTime($checkedInAt),
            'checked_in_by' => User::factory()->create()->id,
            'checked_out_at' => $status === BookingAttendance::STATUS_CHECKED_OUT ? $this->wallTime($checkedOutAt ?? $checkedInAt) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * A transfer received on the venue's connection. With a booking it is paid against that booking's attempt and, when
     * allocated, allocated to it; $purpose names a non-booking payment such as 'pos_order'.
     */
    protected function receipt(Venue $venue, int $amount, CarbonInterface $at, ?Reservation $booking = null, string $status = 'allocated', string $purpose = 'booking', string $transferType = 'in'): int
    {
        $connectionId = $this->receivingConnection($venue);
        $snapshot = Crypt::encryptString('{}');
        $attemptId = null;
        if ($booking !== null) {
            $attemptId = DB::table('owner_payment_attempts')->insertGetId([
                'organization_id' => $venue->organization_id, 'venue_id' => $venue->id, 'receiving_connection_id' => $connectionId,
                'purpose' => $purpose, 'purpose_id' => $booking->code, 'reference' => 'REF'.Str::random(10), 'idempotency_key' => (string) Str::uuid(),
                'request_fingerprint' => hash('sha256', Str::random()), 'amount' => $booking->total_amount, 'status' => 'paid',
                'credential_version' => 1, 'receiver_snapshot_ciphertext' => $snapshot, 'purpose_snapshot_ciphertext' => $snapshot,
                'expires_at' => $this->wallTime($at->copy()->addMinutes(10)), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $evidenceId = DB::table('receiving_connection_evidence')->insertGetId([
            'receiving_connection_id' => $connectionId, 'event_type' => 'webhook_received', 'event_fingerprint' => hash('sha256', Str::random()),
        ]);
        $receiptId = DB::table('owner_payment_receipts')->insertGetId([
            'organization_id' => $venue->organization_id, 'venue_id' => $venue->id, 'receiving_connection_id' => $connectionId,
            'receiving_connection_evidence_id' => $evidenceId, 'payment_attempt_id' => $attemptId,
            'provider_event_id' => (string) Str::uuid(), 'event_fingerprint' => hash('sha256', Str::random()),
            'purpose' => $booking !== null ? $purpose : null, 'purpose_id' => $booking?->code, 'payment_code' => $booking?->code,
            'transfer_type' => $transferType, 'amount' => $amount, 'currency' => 'VND', 'event_at' => $this->wallTime($at),
            'credential_version' => 1, 'status' => $status, 'receiver_snapshot_ciphertext' => $snapshot,
            'received_at' => $this->wallTime($at), 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($booking !== null && $status === 'allocated') {
            DB::table('owner_payment_allocations')->insert([
                'organization_id' => $venue->organization_id, 'venue_id' => $venue->id, 'receiving_connection_id' => $connectionId,
                'payment_attempt_id' => $attemptId, 'payment_receipt_id' => $receiptId, 'purpose' => $purpose, 'purpose_id' => $booking->code,
                'amount' => $amount, 'currency' => 'VND', 'credential_version' => 1, 'receiver_snapshot_ciphertext' => $snapshot,
                'purpose_snapshot_ciphertext' => $snapshot, 'allocated_at' => $this->wallTime($at),
            ]);
        }

        return $receiptId;
    }

    /** The finance columns hold app-timezone wall time (datetime cast), as the code writing them does. */
    protected function wallTime(CarbonInterface $at): CarbonInterface
    {
        return $at->copy()->setTimezone(config('app.timezone'));
    }

    private function receivingConnection(Venue $venue): int
    {
        $existing = DB::table('receiving_connections')->where('venue_id', $venue->id)->value('id');

        return $existing ?? DB::table('receiving_connections')->insertGetId([
            'organization_id' => $venue->organization_id, 'venue_id' => $venue->id, 'owner_user_id' => User::factory()->create()->id,
            'routing_id' => (string) Str::uuid(), 'bank_code' => 'VCB', 'account_name_ciphertext' => Crypt::encryptString('CHU SAN'),
            'account_number_ciphertext' => Crypt::encryptString('0123456789'), 'account_last_four' => '6789', 'status' => 'verified',
            'credential_version' => 1, 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
