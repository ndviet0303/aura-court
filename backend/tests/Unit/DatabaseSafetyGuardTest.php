<?php

namespace Tests\Unit;

use RuntimeException;
use Tests\TestCase;

class DatabaseSafetyGuardTest extends TestCase
{
    public function test_safety_guard_allows_booking_test(): void
    {
        // Should not throw under normal test environment
        $this->ensureSafeTestDatabase();
        $this->assertSame('booking_test', config('database.connections.pgsql.database'));
    }

    public function test_safety_guard_blocks_booking_database(): void
    {
        config(['database.connections.pgsql.database' => 'booking']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("CRITICAL: Test attempted execution against non-test database: 'booking'. Allowed: 'booking_test'.");

        $this->ensureSafeTestDatabase();
    }
}
