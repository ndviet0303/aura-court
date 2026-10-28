<?php

namespace App\Http\Requests\Pricing;

use App\Models\PriceTable;
use Illuminate\Foundation\Http\FormRequest;

/** A new name for a price table (UC-3.5 step 3). */
class RenamePriceTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        // D25: only the owner of the table's organization.
        return (bool) $this->user()?->canManageVenue((int) $this->table()->venue_id);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120']];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên bảng giá.',
            'name.string' => 'Tên bảng giá không hợp lệ.',
            'name.max' => 'Tên bảng giá tối đa 120 ký tự.',
        ];
    }

    public function table(): PriceTable
    {
        /** @var PriceTable $table */
        $table = $this->route('table');

        return $table;
    }
}
