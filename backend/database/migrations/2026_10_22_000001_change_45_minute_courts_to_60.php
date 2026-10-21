<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Court intervals only shape recurring bookings and now take 30, 60, 90 or 120 minutes (D14).
 * 45-minute courts move to 60. The changed ids are printed because the change cannot be undone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = array_map(
            fn (object $row) => $row->id,
            DB::select('UPDATE courts SET slot_duration = 60, updated_at = NOW() WHERE slot_duration = 45 RETURNING id'),
        );

        echo 'Courts changed from 45 to 60 minutes: '.count($ids).($ids === [] ? '' : ' (ids: '.implode(', ', $ids).')').PHP_EOL;
    }

    public function down(): void
    {
        // The old 45-minute ids are only in the output of up().
    }
};
