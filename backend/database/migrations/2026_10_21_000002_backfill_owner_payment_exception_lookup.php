<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Direct-owner payment exceptions now carry what the owner's lookup shows (design D8).
 * Exceptions recorded before that copy it from their receipt and its connection.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('finance_exceptions as f')
            ->join('owner_payment_receipts as r', 'r.id', '=', DB::raw("(f.links::jsonb ->> 'owner_payment_receipt_id')::bigint"))
            ->leftJoin('receiving_connections as c', 'c.id', '=', 'r.receiving_connection_id')
            ->where('f.type', 'like', 'owner\_payment\_%')
            ->where(fn ($query) => $query->whereNull('f.metadata')->orWhereRaw("f.metadata::jsonb -> 'payment_code' IS NULL"))
            ->select('f.id', 'f.metadata', 'r.purpose', 'r.purpose_id', 'r.payment_code', 'r.event_at', 'c.bank_code', 'c.account_last_four')
            ->lazyById(200, 'f.id', 'id');

        foreach ($rows as $row) {
            DB::table('finance_exceptions')->where('id', $row->id)->update(['metadata' => json_encode([
                ...(array) json_decode($row->metadata ?? '{}', true),
                'purpose' => $row->purpose,
                'purpose_id' => $row->purpose_id,
                'payment_code' => $row->payment_code,
                // The receipt column holds app-timezone wall time, as its datetime cast reads it.
                'event_at' => $row->event_at === null ? null : Carbon::parse($row->event_at)->toIso8601String(),
                'bank_code' => $row->bank_code,
                'account_last_four' => $row->account_last_four,
            ])]);
        }
    }

    public function down(): void
    {
        // The copied keys are what new exceptions record too, so there is nothing to tell apart and undo.
    }
};
