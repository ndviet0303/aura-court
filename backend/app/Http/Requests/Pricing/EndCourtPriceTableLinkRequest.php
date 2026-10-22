<?php

namespace App\Http\Requests\Pricing;

use App\Models\Court;
use App\Models\PriceTable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Ends the court's link of one kind at ends_at, now when left out, without a replacement (design D2). */
class EndCourtPriceTableLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Court $court */
        $court = $this->route('court');

        // D25: only the owner of the court's organization changes price links.
        return (bool) $this->user()?->canManageVenue((int) $court->venue_id);
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in([PriceTable::KIND_NORMAL, PriceTable::KIND_HOLIDAY])],
            'ends_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'kind.required' => 'Vui lòng chọn loại bảng giá.',
            'kind.in' => 'Loại bảng giá phải là Thông thường hoặc Ngày lễ.',
            'ends_at.date' => 'Thời điểm kết thúc không hợp lệ.',
        ];
    }
}
