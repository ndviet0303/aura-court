<?php

namespace App\Services\Loyalty;

use App\Models\LoyaltyRule;
use App\Models\MembershipEntitlement;
use App\Models\PointLedger;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Authoritative loyalty point accounting.
 *
 * Points are tracked as append-only ledger lots. Earn entries carry an optional
 * forward-dated (book-ahead) expiry; redemptions and expiries are negative
 * deltas. Available balance is computed with FIFO lot accounting so that expired
 * lots are excluded and retries/concurrent spending cannot overdraw. Expiry is
 * materialised as explicit, idempotent, append-only reversal events; it is never
 * a mutable ledger edit.
 */
class LoyaltyPointService
{
    /** Source type for materialised expiry reversals. */
    public const SOURCE_EXPIRY = 'points_expiry';

    /**
     * Available (spendable) balance excluding expired lots.
     *
     * Replays the ledger in earned order: each lot is expired the moment its
     * expires_at passes, and consumption only draws from still-valid lots. This
     * is correct whether or not the expiry sweep has materialised reversals, so
     * spending never overdraws expired points.
     */
    public function availableBalance(int $organizationId, int $userId, ?CarbonInterface $asOf = null): int
    {
        $asOf = $asOf ? Carbon::parse($asOf) : now();

        $entries = PointLedger::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            // Materialised expiry reversals are excluded: expiry is applied
            // virtually below, so counting them would double-remove the lot.
            ->where('source_type', '!=', self::SOURCE_EXPIRY)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['delta', 'expires_at', 'created_at']);

        /** @var array<int, array{remaining: int, expires_at: ?CarbonInterface}> $lots */
        $lots = [];

        foreach ($entries as $entry) {
            $when = $entry->created_at ?? $asOf;
            $this->expireLotsBy($lots, $when);

            $delta = (int) $entry->delta;
            if ($delta > 0) {
                $lots[] = ['remaining' => $delta, 'expires_at' => $entry->expires_at];

                continue;
            }

            $need = -$delta;
            foreach ($lots as $index => $lot) {
                if ($need <= 0) {
                    break;
                }
                if ($lot['remaining'] <= 0) {
                    continue;
                }
                $take = min($lot['remaining'], $need);
                $lots[$index]['remaining'] -= $take;
                $need -= $take;
            }
        }

        $this->expireLotsBy($lots, $asOf);

        $available = 0;
        foreach ($lots as $lot) {
            if ($lot['remaining'] > 0) {
                $available += $lot['remaining'];
            }
        }

        return $available;
    }

    /**
     * Zero the remaining balance of every lot whose expiry has passed by $moment.
     *
     * @param  array<int, array{remaining: int, expires_at: ?CarbonInterface}>  $lots
     */
    private function expireLotsBy(array &$lots, CarbonInterface $moment): void
    {
        foreach ($lots as $index => $lot) {
            if ($lot['expires_at'] !== null && $lot['expires_at']->lessThanOrEqualTo($moment)) {
                $lots[$index]['remaining'] = 0;
            }
        }
    }

    /**
     * Resolve the active base earn rule for an organization.
     *
     * Multiplier rules (weekend/campaign boosts) are deliberately excluded so
     * earning follows the disclosed base rule plus the member multiplier, never
     * an unrelated newest rule that merely happens to be a multiplier.
     */
    public function getActiveBaseEarnRule(int $organizationId): ?LoyaltyRule
    {
        return LoyaltyRule::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('metadata->is_multiplier_rule')
                    ->orWhere('metadata->is_multiplier_rule', false);
            })
            ->where('name', 'not like', '%Multiplier%')
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The active disclosed membership earn multiplier for a customer (>= 1.0).
     * Returns 1.0 when the customer has no active point_multiplier entitlement.
     */
    public function activeEarnMultiplier(int $organizationId, int $userId, ?CarbonInterface $asOf = null): float
    {
        $asOf = $asOf ? Carbon::parse($asOf) : now();

        $entitlement = MembershipEntitlement::query()
            ->where('organization_id', $organizationId)
            ->where('benefit_code', 'point_multiplier')
            ->where('starts_at', '<=', $asOf)
            ->where('ends_at', '>=', $asOf)
            ->whereHas('membership', fn ($q) => $q
                ->where('organization_id', $organizationId)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->where('starts_at', '<=', $asOf)
                ->where('ends_at', '>=', $asOf))
            ->orderByDesc('id')
            ->first();

        if (! $entitlement) {
            return 1.0;
        }

        $value = $entitlement->benefit_value;
        $multiplier = is_numeric($value)
            ? (float) $value
            : (float) ($value['value'] ?? $value['multiplier'] ?? 1.0);

        return max(1.0, $multiplier);
    }

    /**
     * Compute the forward-dated (book-ahead) expiry for points earned under a
     * rule. The rule's metadata may declare expiry_days or expiry_months; when
     * neither is present the points never expire (null).
     */
    public function resolveExpiresAt(LoyaltyRule $rule, ?CarbonInterface $earnedAt = null): ?Carbon
    {
        $earnedAt = $earnedAt ? Carbon::parse($earnedAt) : now();
        $meta = $rule->metadata ?? [];

        $days = (int) ($meta['expiry_days'] ?? 0);
        $months = (int) ($meta['expiry_months'] ?? 0);

        if ($months > 0) {
            return $earnedAt->copy()->addMonths($months);
        }
        if ($days > 0) {
            return $earnedAt->copy()->addDays($days);
        }

        return null;
    }

    /**
     * Resolve points earned for a qualifying transaction: base earn rule times
     * the active member multiplier, applied once, floored to a whole point.
     *
     * @return array{points: int, base_points: int, multiplier: float, rule: LoyaltyRule, expires_at: ?Carbon}
     */
    public function resolveEarnPoints(int $organizationId, int $userId, int $amountVnd): array
    {
        $rule = $this->getActiveBaseEarnRule($organizationId);
        abort_if($rule === null, 422, 'No active base loyalty earn rule is configured.');

        $basePoints = intdiv(max(0, $amountVnd) * (int) $rule->earn_points_per_100_vnd, 100);
        $multiplier = $this->activeEarnMultiplier($organizationId, $userId);
        $points = (int) floor($basePoints * $multiplier);

        return [
            'points' => $points,
            'base_points' => $basePoints,
            'multiplier' => $multiplier,
            'rule' => $rule,
            'expires_at' => $this->resolveExpiresAt($rule),
        ];
    }

    /**
     * Materialise append-only expiry reversals for every lot that has expired
     * with an unconsumed remainder. Idempotent: each earn lot is reversed at
     * most once (keyed by its ledger id), so retries never double-expire.
     *
     * @return int number of expiry reversal entries written
     */
    public function expireDuePoints(?int $organizationId = null, ?CarbonInterface $asOf = null): int
    {
        $asOf = $asOf ? Carbon::parse($asOf) : now();

        $scopeKeys = PointLedger::query()
            ->when($organizationId !== null, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('source_type', '!=', self::SOURCE_EXPIRY)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $asOf)
            ->where('delta', '>', 0)
            ->select('organization_id', 'user_id')
            ->distinct()
            ->get();

        $written = 0;
        foreach ($scopeKeys as $key) {
            $written += $this->expireDuePointsForCustomer((int) $key->organization_id, (int) $key->user_id, $asOf);
        }

        return $written;
    }

    private function expireDuePointsForCustomer(int $organizationId, int $userId, CarbonInterface $asOf): int
    {
        return DB::transaction(function () use ($organizationId, $userId, $asOf): int {
            $entries = PointLedger::query()
                ->where('organization_id', $organizationId)
                ->where('user_id', $userId)
                ->where('source_type', '!=', self::SOURCE_EXPIRY)
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'delta', 'expires_at', 'loyalty_rule_id']);

            // Replay to find each earn lot's unconsumed remainder as of $asOf.
            $lots = [];
            foreach ($entries as $entry) {
                $delta = (int) $entry->delta;
                if ($delta > 0) {
                    $lots[] = ['id' => (int) $entry->id, 'rule_id' => $entry->loyalty_rule_id, 'remaining' => $delta, 'expires_at' => $entry->expires_at];

                    continue;
                }
                $need = -$delta;
                foreach ($lots as $index => $lot) {
                    if ($need <= 0) {
                        break;
                    }
                    if ($lot['remaining'] <= 0) {
                        continue;
                    }
                    $take = min($lot['remaining'], $need);
                    $lots[$index]['remaining'] -= $take;
                    $need -= $take;
                }
            }

            $alreadyExpired = PointLedger::query()
                ->where('organization_id', $organizationId)
                ->where('user_id', $userId)
                ->where('source_type', self::SOURCE_EXPIRY)
                ->pluck('source_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $running = (int) PointLedger::query()
                ->where('organization_id', $organizationId)
                ->where('user_id', $userId)
                ->sum('delta');

            $written = 0;
            foreach ($lots as $lot) {
                if ($lot['remaining'] <= 0) {
                    continue;
                }
                if ($lot['expires_at'] === null || $lot['expires_at']->greaterThan($asOf)) {
                    continue;
                }
                if (in_array($lot['id'], $alreadyExpired, true)) {
                    continue;
                }

                $running -= $lot['remaining'];
                PointLedger::create([
                    'organization_id' => $organizationId,
                    'user_id' => $userId,
                    'loyalty_rule_id' => $lot['rule_id'],
                    'delta' => -$lot['remaining'],
                    'balance_after' => $running,
                    'source_type' => self::SOURCE_EXPIRY,
                    'source_id' => $lot['id'],
                    'expires_at' => $lot['expires_at'],
                    'metadata' => [
                        'expired_lot_ledger_id' => $lot['id'],
                        'expired_at' => $asOf->toIso8601String(),
                    ],
                ]);
                $written++;
            }

            return $written;
        });
    }
}
