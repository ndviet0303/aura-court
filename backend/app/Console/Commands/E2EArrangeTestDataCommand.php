<?php

namespace App\Console\Commands;

use App\Models\Coupon;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\LoyaltyRule;
use App\Models\Organization;
use App\Models\PointLedger;
use App\Models\PosProduct;
use App\Models\PriceRule;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class E2EArrangeTestDataCommand extends Command
{
    protected $signature = 'e2e:arrange-test-data
        {--run-id= : Unique run ID}
        {--scenario= : Scenario type (court|coupon|loyalty_points|pos_stock)}
        {--payload-json= : JSON payload for scenario}';

    protected $description = 'Arrange isolated per-test data for court, pricing, coupon, points, or stock';

    public function handle(): int
    {
        $runId = trim((string) $this->option('run-id'));
        if ($runId === '' || ! preg_match('/^[a-zA-Z0-9_-]+$/', $runId)) {
            $this->error('CRITICAL: Missing or invalid --run-id');
            return self::FAILURE;
        }

        // Strict target safety check before any mutation
        $env = app()->environment();
        if ($env !== 'testing' && $env !== 'local') {
            $this->error("CRITICAL: Refusing to mutate target in environment '{$env}'. Only 'testing' or 'local' allowed.");
            return self::FAILURE;
        }

        $dbConfig = DB::connection()->getConfig();
        $dbHost = (string) ($dbConfig['host'] ?? '');
        $dbName = (string) ($dbConfig['database'] ?? '');

        $allowedHosts = ['127.0.0.1', 'localhost', '::1', 'bt-server', '100.64.0.15', (string) config('database.connections.'.config('database.default').'.host')];
        if (! in_array($dbHost, $allowedHosts, true)) {
            $this->error("CRITICAL: Refusing remote target '{$dbHost}'.");
            return self::FAILURE;
        }

        $isProdRun = preg_match('/^prod_e2e_[0-9]+_[a-f0-9]{8}$/', $runId) === 1;
        if (! $isProdRun) {
            $expectedDb = "booking_e2e_{$runId}_test";
            if ($dbName === 'booking' || $dbName === 'booking_test') {
                $this->error("CRITICAL: Refusing to arrange test data in protected database '{$dbName}'.");
                return self::FAILURE;
            }

            if ($dbName !== $expectedDb) {
                $this->error("CRITICAL: Active database '{$dbName}' does not match expected '{$expectedDb}'.");
                return self::FAILURE;
            }
        }
        $scenario = trim((string) $this->option('scenario'));
        $payloadJson = (string) $this->option('payload-json');
        $payload = $payloadJson !== '' ? (json_decode($payloadJson, true) ?: []) : [];

        if ($isProdRun) {
            $organizationId = (int) ($payload['organization_id'] ?? 0);
            if (! empty($payload['venue_id'])) {
                $organizationId = (int) Venue::findOrFail($payload['venue_id'])->organization_id;
            }
            if (! Organization::whereKey($organizationId)->where('settings->e2e_run_id', $runId)->exists()) {
                $this->error('Production arrangement is restricted to the current run-owned test organization.');
                return self::FAILURE;
            }
        }

        return DB::transaction(function () use ($runId, $scenario, $payload) {
            switch ($scenario) {
                case 'court':
                    return $this->arrangeCourt($runId, $payload);
                case 'coupon':
                    return $this->arrangeCoupon($runId, $payload);
                case 'loyalty_points':
                    return $this->arrangeLoyaltyPoints($runId, $payload);
                case 'pos_stock':
                    return $this->arrangePosStock($runId, $payload);
                default:
                    $this->error("Unknown scenario: {$scenario}");
                    return self::FAILURE;
            }
        });
    }

    private function arrangeCourt(string $runId, array $payload): int
    {
        $testKey = $payload['test_key'] ?? 'test_' . uniqid();
        $venueId = $payload['venue_id'] ?? null;
        if (! $venueId) {
            $venue = Venue::where('slug', 'like', "%venue-1a%")->first() ?: Venue::firstOrFail();
            $venueId = $venue->id;
        } else {
            $venue = Venue::findOrFail($venueId);
        }

        $basePrice = (int) ($payload['price'] ?? 100000);
        $courtName = "Court {$testKey} {$runId}";
        $courtSlug = "court-" . Str::slug($testKey) . "-{$runId}-" . Str::lower(Str::random(6));
        $court = Court::create([
            'venue_id' => $venue->id,
            'name' => $courtName,
            'slug' => $courtSlug,
            'is_active' => true,
        ]);

        PriceRule::create([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->id,
            'court_id' => $court->id,
            'type' => 'regular',
            'price' => $basePrice,
            'version' => 1,
            'priority' => 10,
            'is_active' => true,
        ]);

        $slots = [];
        for ($weekday = 1; $weekday <= 7; $weekday++) {
            for ($hour = 6; $hour <= 22; $hour++) {
                $slots[] = [
                    'court_id' => $court->id,
                    'weekday' => $weekday,
                    'starts_at' => sprintf('%02d:00', $hour),
                    'ends_at' => sprintf('%02d:00', $hour + 1),
                    'price' => $basePrice,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        CourtPriceSlot::insert($slots);
        \Illuminate\Support\Facades\Cache::increment('courts_version');

        $out = [
            'status' => 'success',
            'court_id' => $court->id,
            'name' => $court->name,
            'slug' => $court->slug,
            'venue_id' => $venue->id,
            'organization_id' => $venue->organization_id,
            'price' => $basePrice,
        ];

        $this->output->writeln(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }

    private function arrangeCoupon(string $runId, array $payload): int
    {
        $testKey = $payload['test_key'] ?? 'CPN_' . strtoupper(uniqid());
        $orgId = $payload['organization_id'] ?? null;
        if (! $orgId) {
            $org = Organization::where('slug', 'like', "%aura-primary%")->first() ?: Organization::firstOrFail();
            $orgId = $org->id;
        }

        $defaultCode = "CPN_" . substr(strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $testKey)), 0, 16) . "_" . substr($runId, -8) . "_" . Str::upper(Str::random(4));
        $code = strtoupper(preg_replace('/[^a-zA-Z0-9_-]/', '', $payload['code'] ?? $defaultCode));
        if (strlen($code) > 36) {
            $code = substr($code, 0, 36);
        }
        $discountType = $payload['discount_type'] ?? 'fixed';
        $discountValue = (int) ($payload['discount_value'] ?? 20000);
        $minSpend = (int) ($payload['min_spend'] ?? 50000);
        $usageLimit = (int) ($payload['usage_limit'] ?? 10);

        $coupon = Coupon::create([
            'organization_id' => $orgId,
            'venue_id' => $payload['venue_id'] ?? null,
            'code' => $code,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'max_discount' => $discountType === 'percent' ? 100000 : null,
            'min_spend' => $minSpend,
            'usage_limit' => $usageLimit,
            'usage_count' => 0,
            'status' => Coupon::STATUS_ACTIVE,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
            'version' => 1,
        ]);

        $out = [
            'status' => 'success',
            'coupon_id' => $coupon->id,
            'code' => $coupon->code,
            'discount_type' => $coupon->discount_type,
            'discount_value' => $coupon->discount_value,
            'organization_id' => $orgId,
        ];

        $this->output->writeln(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }

    private function arrangeLoyaltyPoints(string $runId, array $payload): int
    {
        $userId = $payload['user_id'] ?? null;
        if (! $userId) {
            $user = User::where('email', 'like', "customer_{$runId}%")->first() ?: User::firstOrFail();
            $userId = $user->id;
        }

        $orgId = $payload['organization_id'] ?? null;
        if (! $orgId) {
            $org = Organization::where('slug', 'like', "%aura-primary%")->first() ?: Organization::firstOrFail();
            $orgId = $org->id;
        }

        $points = (int) ($payload['points'] ?? 100);

        // Ensure active base loyalty rule exists in org
        $loyaltyRule = LoyaltyRule::firstOrCreate(
            ['organization_id' => $orgId, 'status' => 'published'],
            [
                'name' => "Standard Loyalty Program {$runId}",
                'version' => 1,
                'earn_points_per_100_vnd' => 1,
                'redeem_points_per_100_vnd' => 1,
                'published_at' => now()->subDays(5),
                'metadata' => [
                    'points_redeemed' => 1,
                    'reward_value_vnd' => 1000,
                ],
            ]
        );

        $currentBalance = (int) PointLedger::where('organization_id', $orgId)->where('user_id', $userId)->sum('delta');
        $newBalance = $currentBalance + $points;

        $entry = PointLedger::create([
            'organization_id' => $orgId,
            'user_id' => $userId,
            'loyalty_rule_id' => $loyaltyRule->id,
            'delta' => $points,
            'balance_after' => $newBalance,
            'source_type' => 'test_initial_grant',
            'source_id' => (int) (microtime(true) * 1000),
            'expires_at' => now()->addMonths(6),
            'metadata' => ['run_id' => $runId, 'arranged_for_test' => true],
        ]);

        $out = [
            'status' => 'success',
            'user_id' => $userId,
            'organization_id' => $orgId,
            'granted_points' => $points,
            'current_balance' => $newBalance,
            'ledger_id' => $entry->id,
        ];

        $this->output->writeln(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }

    private function arrangePosStock(string $runId, array $payload): int
    {
        $testKey = $payload['test_key'] ?? 'pos_' . uniqid();
        $orgId = $payload['organization_id'] ?? null;
        if (! $orgId) {
            $org = Organization::where('slug', 'like', "%aura-primary%")->first() ?: Organization::firstOrFail();
            $orgId = $org->id;
        }

        $sku = strtoupper($payload['sku'] ?? "SKU-{$testKey}-{$runId}");
        $name = $payload['name'] ?? "Test Drink {$testKey}";
        $price = (int) ($payload['price'] ?? 20000);
        $cost = (int) ($payload['cost'] ?? 10000);
        $stockQuantity = (int) ($payload['stock_quantity'] ?? 50);

        $product = PosProduct::updateOrCreate(
            ['organization_id' => $orgId, 'sku' => $sku],
            [
                'name' => $name,
                'price' => $price,
                'cost' => $cost,
                'stock_quantity' => $stockQuantity,
                'is_active' => true,
                'metadata' => [
                    'category' => $payload['category'] ?? 'drinks',
                    'unit' => $payload['unit'] ?? 'chai',
                    'min_stock' => 5,
                    'track_stock' => true,
                ],
            ]
        );

        $out = [
            'status' => 'success',
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'price' => $product->price,
            'stock_quantity' => $product->stock_quantity,
            'organization_id' => $orgId,
        ];

        $this->output->writeln(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
