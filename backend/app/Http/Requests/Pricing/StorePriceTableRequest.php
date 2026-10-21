<?php

namespace App\Http\Requests\Pricing;

use App\Models\PriceTable;
use App\Traits\ValidatesPriceIntervals;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A price table with its first version (design D1); the intervals follow ValidatesPriceIntervals. */
class StorePriceTableRequest extends FormRequest
{
    use ValidatesPriceIntervals;

    public function authorize(): bool
    {
        // D25: only the owner of the venue's organization manages its prices.
        return (bool) $this->user()?->canManageVenue($this->route('venue'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in([PriceTable::KIND_NORMAL, PriceTable::KIND_HOLIDAY])],
            'effective_at' => ['required', 'date'],
            ...$this->intervalRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên bảng giá.',
            'name.max' => 'Tên bảng giá tối đa 120 ký tự.',
            'kind.required' => 'Vui lòng chọn loại bảng giá.',
            'kind.in' => 'Loại bảng giá phải là Thông thường hoặc Ngày lễ.',
            'effective_at.required' => 'Vui lòng chọn thời điểm hiệu lực.',
            'effective_at.date' => 'Thời điểm hiệu lực không hợp lệ.',
            ...$this->intervalMessages(),
        ];
    }

    protected function intervalKind(): ?string
    {
        $kind = $this->input('kind');

        return in_array($kind, [PriceTable::KIND_NORMAL, PriceTable::KIND_HOLIDAY], true) ? $kind : null;
    }
}
