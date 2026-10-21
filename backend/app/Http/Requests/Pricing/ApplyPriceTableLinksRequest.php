<?php

namespace App\Http\Requests\Pricing;

use App\Models\PriceTable;
use Illuminate\Foundation\Http\FormRequest;

/** Courts of the table's venue that use the table from starts_at, now when left out (design D2). */
class ApplyPriceTableLinksRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PriceTable $table */
        $table = $this->route('table');

        // D25: only the owner of the table's organization changes price links.
        return (bool) $this->user()?->canManageVenue((int) $table->venue_id);
    }

    public function rules(): array
    {
        return [
            'court_ids' => ['required', 'list', 'min:1'],
            'court_ids.*' => ['integer', 'distinct'],
            'starts_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'court_ids.required' => 'Vui lòng chọn ít nhất một sân.',
            'court_ids.min' => 'Vui lòng chọn ít nhất một sân.',
            'court_ids.list' => 'Danh sách sân không hợp lệ.',
            'court_ids.*' => 'Danh sách sân không hợp lệ.',
            'starts_at.date' => 'Thời điểm áp dụng không hợp lệ.',
        ];
    }
}
