<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * New bookings record who booked them (BR-75). Bookings made before that copy the booker from today's profile,
 * flagged as backfilled because the profile may have changed since.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE reservations AS r
            SET metadata = (
                COALESCE(r.metadata::jsonb, '{}'::jsonb)
                || jsonb_build_object(
                    'booker', jsonb_build_object('name', u.name, 'phone', u.phone, 'email', u.email),
                    'booker_backfilled', true
                )
            )::json
            FROM users AS u
            WHERE u.id = r.user_id
              AND (r.metadata IS NULL OR r.metadata::jsonb -> 'booker' IS NULL)
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            UPDATE reservations
            SET metadata = (metadata::jsonb - 'booker' - 'booker_backfilled')::json
            WHERE metadata::jsonb -> 'booker_backfilled' = 'true'::jsonb
            SQL);
    }
};
