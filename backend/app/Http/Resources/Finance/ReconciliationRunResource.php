<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $details = is_array($this->details) ? $this->details : (json_decode($this->details ?? '[]', true) ?: []);
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'run_date' => $this->run_date?->toDateString(),
            'status' => $this->status,
            'provider_total' => (int) $this->provider_total,
            'journal_total' => (int) $this->journal_total,
            'settlement_total' => (int) $this->settlement_total,
            'payout_total' => (int) $this->payout_total,
            'mismatch_amount' => (int) $this->mismatch_amount,
            'details' => $details,
            'channel' => $details['channel'] ?? 'VietQR Pro (MBBank)',
            'txnCode' => $details['txnCode'] ?? "REC-{$this->id}",
            'riskLevel' => $details['riskLevel'] ?? ($this->status === 'mismatch' ? 'high' : 'low'),
            'statusLabel' => $details['statusLabel'] ?? ($this->status === 'matched' ? 'Đã khớp 100%' : 'Lệch số tiền'),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
