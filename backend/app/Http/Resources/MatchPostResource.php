<?php

namespace App\Http\Resources;

use App\Models\MatchPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MatchPost
 */
class MatchPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        if ($this->resource instanceof MatchPost) {
            $targetTz = $request->input('display_timezone') ?? $request->header('X-Display-Timezone');
            if ($targetTz) {
                if ($this->starts_at) {
                    $data['starts_at'] = $this->starts_at->copy()->setTimezone($targetTz)->toIso8601String();
                }
                if ($this->ends_at) {
                    $data['ends_at'] = $this->ends_at->copy()->setTimezone($targetTz)->toIso8601String();
                }
                if ($this->registration_deadline) {
                    $data['registration_deadline'] = $this->registration_deadline->copy()->setTimezone($targetTz)->toIso8601String();
                }
                if ($this->roster_closed_at) {
                    $data['roster_closed_at'] = $this->roster_closed_at->copy()->setTimezone($targetTz)->toIso8601String();
                }
            } else {
                if ($this->starts_at) {
                    $data['starts_at'] = $this->starts_at->toIso8601String();
                }
                if ($this->ends_at) {
                    $data['ends_at'] = $this->ends_at->toIso8601String();
                }
                if ($this->registration_deadline) {
                    $data['registration_deadline'] = $this->registration_deadline->toIso8601String();
                }
                if ($this->roster_closed_at) {
                    $data['roster_closed_at'] = $this->roster_closed_at->toIso8601String();
                }
            }
        }

        return $data;
    }
}
