<?php

namespace Tests\Feature\Infrastructure;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialHistoryDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_an_organization_with_a_pos_order_is_rejected(): void
    {
        $organizationId = $this->organization();
        DB::table('pos_orders')->insert([
            'organization_id' => $organizationId,
            'cashier_id' => User::factory()->create()->id,
            'payment_method' => 'cash',
            'subtotal' => 50000,
            'total_amount' => 50000,
        ]);

        $this->assertDeletionRejected(
            fn () => DB::table('organizations')->where('id', $organizationId)->delete(),
            'pos_orders_organization_id_foreign',
        );

        $this->assertDatabaseHas('organizations', ['id' => $organizationId]);
        $this->assertSame(1, DB::table('pos_orders')->where('organization_id', $organizationId)->count());
    }

    public function test_deleting_a_user_with_a_wallet_is_rejected(): void
    {
        $user = User::factory()->create();
        DB::table('wallet_accounts')->insert(['organization_id' => $this->organization(), 'user_id' => $user->id]);

        $this->assertDeletionRejected(
            fn () => DB::table('users')->where('id', $user->id)->delete(),
            'wallet_accounts_user_id_foreign',
        );

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertSame(1, DB::table('wallet_accounts')->where('user_id', $user->id)->count());
    }

    public function test_deleting_a_user_with_a_point_ledger_is_rejected(): void
    {
        $user = User::factory()->create();
        DB::table('point_ledgers')->insert([
            'organization_id' => $this->organization(),
            'user_id' => $user->id,
            'delta' => 10,
            'balance_after' => 10,
            'source_type' => 'reservation',
            'source_id' => 1,
        ]);

        $this->assertDeletionRejected(
            fn () => DB::table('users')->where('id', $user->id)->delete(),
            'point_ledgers_user_id_foreign',
        );

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertSame(1, DB::table('point_ledgers')->where('user_id', $user->id)->count());
    }

    public function test_deleting_an_organization_with_a_journal_is_rejected_by_the_foreign_key(): void
    {
        $organizationId = $this->organization();
        DB::table('journals')->insert([
            'organization_id' => $organizationId,
            'event_type' => 'test.posting',
            'total_debit' => 1000,
            'total_credit' => 1000,
            'posted_at' => now(),
        ]);

        $exception = $this->assertDeletionRejected(
            fn () => DB::table('organizations')->where('id', $organizationId)->delete(),
            'journals_organization_id_foreign',
        );

        // A cascade would have reached the append-only trigger instead.
        $this->assertStringNotContainsString('append-only', $exception->getMessage());
        $this->assertDatabaseHas('organizations', ['id' => $organizationId]);
        $this->assertSame(1, DB::table('journals')->where('organization_id', $organizationId)->count());
    }

    private function organization(): int
    {
        $slug = 'fin-history-'.uniqid();

        return DB::table('organizations')->insertGetId(['name' => $slug, 'slug' => $slug]);
    }

    private function assertDeletionRejected(callable $delete, string $constraint): QueryException
    {
        try {
            // Savepoint, so the failed statement does not abort the test transaction.
            DB::transaction($delete);
        } catch (QueryException $exception) {
            $this->assertSame('23503', $exception->getCode());
            $this->assertStringContainsString($constraint, $exception->getMessage());

            return $exception;
        }

        $this->fail("Expected the delete to be rejected by {$constraint}.");
    }
}
