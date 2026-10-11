<?php

/**
 * Script: verify_legacy_inventory.php
 * Task 1.5: Read-only inventory and additive migration/payment_model classification verification.
 *
 * Verifies legacy financial accounts, bookings, and ledger entries without mutating any data.
 * Computes deterministic checksums and ledger balances.
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

echo "=================================================================\n";
echo "Task 1.5: Read-Only Legacy Inventory & Checksum Verification\n";
echo 'Database: '.config('database.connections.pgsql.database')."\n";
echo 'Time:     '.date('Y-m-d H:i:s T')."\n";
echo "=================================================================\n\n";

// 1. Wallet Accounts & Balances Inventory
$walletStats = DB::table('wallet_accounts')
    ->selectRaw('COUNT(*) as total_accounts, COALESCE(SUM(balance), 0) as total_balance')
    ->first();

$walletChecksumRaw = DB::table('wallet_accounts')
    ->orderBy('id')
    ->select('id', 'organization_id', 'user_id', 'currency', 'balance', 'status')
    ->get()
    ->toJson();
$walletChecksum = hash('sha256', $walletChecksumRaw);

echo "[1] WALLET ACCOUNTS:\n";
echo '    - Total Accounts: '.($walletStats->total_accounts ?? 0)."\n";
echo '    - Total Balance:  '.number_format((float) ($walletStats->total_balance ?? 0))." VND\n";
echo '    - SHA-256 Hash:   '.$walletChecksum."\n\n";

// 2. Wallet Transactions (Historical Ledger)
$txStats = DB::table('wallet_transactions')
    ->selectRaw('COUNT(*) as total_tx, COALESCE(SUM(amount), 0) as total_volume')
    ->first();

$txChecksumRaw = DB::table('wallet_transactions')
    ->orderBy('id')
    ->select('id', 'organization_id', 'wallet_account_id', 'type', 'amount', 'balance_after')
    ->get()
    ->toJson();
$txChecksum = hash('sha256', $txChecksumRaw);

echo "[2] WALLET TRANSACTIONS:\n";
echo '    - Total Tx Count: '.($txStats->total_tx ?? 0)."\n";
echo '    - Total Volume:   '.number_format((float) ($txStats->total_volume ?? 0))." VND\n";
echo '    - SHA-256 Hash:   '.$txChecksum."\n\n";

// 3. Bookings Classification (legacy_wallet_v1 vs direct_owner_v1)
$totalBookings = DB::table('bookings')->count();

$legacyBookings = DB::table('bookings')
    ->where(function ($query) {
        $query->whereNull('metadata')
            ->orWhereRaw("metadata->>'payment_model' IS NULL")
            ->orWhereRaw("metadata->>'payment_model' = 'legacy_wallet_v1'");
    })
    ->count();

$directOwnerBookings = DB::table('bookings')
    ->whereRaw("metadata->>'payment_model' = 'direct_owner_v1'")
    ->count();

echo "[3] BOOKINGS PAYMENT MODEL CLASSIFICATION:\n";
echo "    - Total Bookings:        {$totalBookings}\n";
echo "    - Legacy Wallet Bookings: {$legacyBookings} (classified as legacy_wallet_v1)\n";
echo "    - Direct Owner Bookings:  {$directOwnerBookings} (classified as direct_owner_v1)\n\n";

// 4. Financial Journals Balancing Invariant
$journalStats = DB::table('journals')
    ->selectRaw('COUNT(*) as total_journals, COALESCE(SUM(total_debit), 0) as sum_debit, COALESCE(SUM(total_credit), 0) as sum_credit')
    ->first();

$isBalanced = ((int) ($journalStats->sum_debit ?? 0)) === ((int) ($journalStats->sum_credit ?? 0));

echo "[4] FINANCIAL JOURNALS AUDIT:\n";
echo '    - Total Journals: '.($journalStats->total_journals ?? 0)."\n";
echo '    - Sum Debit:      '.number_format((float) ($journalStats->sum_debit ?? 0))." VND\n";
echo '    - Sum Credit:     '.number_format((float) ($journalStats->sum_credit ?? 0))." VND\n";
echo '    - Balanced State: '.($isBalanced ? 'BALANCED (Invariants hold)' : 'MISMATCH (Warning: Debit != Credit)')."\n\n";

// 5. Entitlements & Points
$pointsTotal = DB::table('point_ledgers')->count();
$membershipsTotal = DB::table('tenant_memberships')->count();

echo "[5] LOYALTY & ENTITLEMENTS:\n";
echo "    - Point Ledger Entries: {$pointsTotal}\n";
echo "    - Tenant Memberships:   {$membershipsTotal}\n\n";

echo "=================================================================\n";
echo "Verification Complete: All operations were strictly READ-ONLY.\n";
echo "=================================================================\n";

exit(0);
