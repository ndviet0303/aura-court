<?php

namespace App\Http\Requests\Pricing;

use App\Models\PriceTable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Retiring a table (design D2), optionally with a replacement table for some of its courts from a switch time, now
 * when left out, saved in the same confirmation (UC-3.6 A-1).
 */
class RetirePriceTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        // D25: only the owner of the table's organization.
        return (bool) $this->user()?->canManageVenue((int) $this->table()->venue_id);
    }

    public function rules(): array
    {
        return [
            'replacement_table_id' => ['nullable', 'integer', 'not_in:'.$this->table()->id],
            'court_ids' => ['required_with:replacement_table_id', 'prohibited_without:replacement_table_id', 'list', 'min:1'],
            'court_ids.*' => ['integer', 'distinct'],
            'starts_at' => ['nullable', 'prohibited_without:replacement_table_id', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'replacement_table_id.integer' => 'Bảng giá thay thế không hợp lệ.',
            'replacement_table_id.not_in' => 'Bảng giá thay thế phải khác bảng giá đang ngừng.',
            'court_ids.required_with' => 'Vui lòng chọn sân chuyển sang bảng giá thay thế.',
            'court_ids.min' => 'Vui lòng chọn sân chuyển sang bảng giá thay thế.',
            'court_ids.list' => 'Danh sách sân không hợp lệ.',
            'court_ids.*' => 'Danh sách sân không hợp lệ.',
            'starts_at.date' => 'Thời điểm chuyển bảng giá không hợp lệ.',
        ];
    }

    public function table(): PriceTable
    {
        /** @var PriceTable $table */
        $table = $this->route('table');

        return $table;
    }
}
