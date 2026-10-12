<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationStatsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'provider_total' => (int) $this->resource['provider_total'],
            'journal_total' => (int) $this->resource['journal_total'],
            'settlement_total' => (int) $this->resource['settlement_total'],
            'payout_total' => (int) $this->resource['payout_total'],
            'mismatch_total' => (int) $this->resource['mismatch_total'],
            'open_alerts_count' => (int) $this->resource['open_alerts_count'],
            'match_rate' => isset($this->resource['match_rate']) ? (float) $this->resource['match_rate'] : null,
            'last_reconciled_at' => $this->resource['last_reconciled_at'],
            'sync_status' => $this->resource['sync_status'],
            'health_trend' => $this->resource['health_trend'] ?? [],
        ];
    }
}
