<?php

namespace App\Http\Requests\POS;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer'],
            'from_venue_id' => ['required', 'integer'],
            'to_venue_id' => ['required', 'integer', 'different:from_venue_id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'to_venue_id.different' => 'Cơ sở nhận phải khác cơ sở chuyển.',
            'quantity.min' => 'Số lượng điều chuyển phải lớn hơn 0.',
        ];
    }
}
