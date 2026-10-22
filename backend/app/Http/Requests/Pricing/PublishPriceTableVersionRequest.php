<?php

namespace App\Http\Requests\Pricing;

use App\Models\PriceTable;
use App\Traits\ValidatesPriceIntervals;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A new version of a price table (design D1, D6): intervals of the table's kind, and the time it takes effect,
 * left out, past or now meaning at the publication.
 */
class PublishPriceTableVersionRequest extends FormRequest
{
    use ValidatesPriceIntervals;

    public function authorize(): bool
    {
        // D25: only the owner of the table's organization publishes its prices.
        return (bool) $this->user()?->canManageVenue((int) $this->table()->venue_id);
    }

    public function rules(): array
    {
        return ['effective_at' => ['nullable', 'date'], ...$this->intervalRules()];
    }

    public function messages(): array
    {
        return ['effective_at.date' => 'Thời điểm hiệu lực không hợp lệ.', ...$this->intervalMessages()];
    }

    public function table(): PriceTable
    {
        /** @var PriceTable $table */
        $table = $this->route('table');

        return $table;
    }

    protected function intervalKind(): ?string
    {
        return (string) $this->table()->kind;
    }
}
