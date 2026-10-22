<?php

namespace App\Http\Requests\Promotion;

use App\Models\PromotionProgram;
use App\Traits\AuthorizesPromotionVenue;
use App\Traits\ValidatesPromotionTerms;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A new promotion program of the venue (design D7, D8); its terms follow ValidatesPromotionTerms. */
class StorePromotionProgramRequest extends FormRequest
{
    use AuthorizesPromotionVenue;
    use ValidatesPromotionTerms;

    public function authorize(): bool
    {
        return $this->managesPromotionVenue($this, $this->route('venue'));
    }

    public function rules(): array
    {
        $type = $this->input('type');
        $kind = $this->input('discount_kind');

        return [
            'type' => ['required', Rule::in([PromotionProgram::TYPE_PUBLIC, PromotionProgram::TYPE_PRIVATE])],
            'discount_kind' => ['required', Rule::in([PromotionProgram::KIND_PERCENT, PromotionProgram::KIND_FIXED])],
            ...$this->termRules(is_string($kind) ? $kind : null, is_string($type) ? $type : null, false),
            'code_count' => $type === PromotionProgram::TYPE_PUBLIC
                ? ['prohibited']
                : [Rule::requiredIf($type === PromotionProgram::TYPE_PRIVATE), 'integer', 'min:1', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Vui lòng chọn loại voucher.',
            'type.in' => 'Loại voucher phải là Công khai hoặc Riêng.',
            'discount_kind.required' => 'Vui lòng chọn hình thức giảm.',
            'discount_kind.in' => 'Hình thức giảm phải là phần trăm hoặc số tiền cố định.',
            'code_count.required' => 'Vui lòng nhập số mã cần phát hành.',
            'code_count.integer' => 'Số mã phải là số nguyên.',
            'code_count.min' => 'Số mã phải lớn hơn 0.',
            'code_count.max' => 'Mỗi chương trình phát hành tối đa 5.000 mã.',
            'code_count.prohibited' => 'Chương trình công khai không có mã riêng; hãy nhập tổng lượt sử dụng.',
            ...$this->termMessages(),
        ];
    }
}
