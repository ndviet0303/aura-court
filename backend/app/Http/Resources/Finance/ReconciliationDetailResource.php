<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'run' => new ReconciliationRunResource($this->resource['run']),
            'provider_events' => $this->resource['provider_events'],
            'journals' => $this->resource['journals'],
            'ai_diagnosis' => $this->resource['ai_diagnosis'],
            'details' => $this->resource['details'] ?? [],
        ];
    }
}
