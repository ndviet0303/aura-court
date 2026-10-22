<?php

namespace App\Traits;

use App\Models\PromotionProgram;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * The editable terms of a promotion program (design D8, D14, D27), shared by the form requests that create and
 * update one: a percentage above 0 and at most 100 with at most two decimals, or a whole VND amount above 0; a
 * maximum discount only for a percentage; a window whose end follows its start; a non-empty court set; and total
 * uses for a public program. Fields of the other discount kind or voucher kind are refused, not ignored. It sits
 * outside Http/Requests, where every file must be a form request.
 */
trait ValidatesPromotionTerms
{
    // The money columns are 32-bit integers; a billion VND is far beyond any rental.
    private const MAX_VND = 1000000000;

    /**
     * Rules for the program's discount kind and voucher kind, null when the request names no valid one. $partial
     * (an update) makes every field optional; the action then checks the window against the stored end or start.
     *
     * @return array<string, list<mixed>>
     */
    protected function termRules(?string $discountKind, ?string $type, bool $partial): array
    {
        $percent = $discountKind === PromotionProgram::KIND_PERCENT;
        $fixed = $discountKind === PromotionProgram::KIND_FIXED;
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'percent' => $fixed ? ['prohibited'] : [Rule::requiredIf($percent), 'numeric', 'gt:0', 'lte:100', 'decimal:0,2'],
            'amount' => $percent ? ['prohibited'] : [Rule::requiredIf($fixed), 'integer', 'min:1', 'max:'.self::MAX_VND],
            'max_discount' => $fixed ? ['prohibited'] : ['nullable', 'integer', 'min:1', 'max:'.self::MAX_VND],
            'min_rental' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_VND],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', ...(! $partial || $this->has('starts_at') ? ['after:starts_at'] : [])],
            'court_ids' => ['required', 'list', 'min:1'],
            'court_ids.*' => ['integer', 'distinct'],
            'max_uses' => $type === PromotionProgram::TYPE_PRIVATE
                ? ['prohibited']
                : [Rule::requiredIf($type === PromotionProgram::TYPE_PUBLIC), 'integer', 'min:1', 'max:1000000'],
        ];

        return $partial ? array_map(fn (array $field): array => ['sometimes', ...$field], $rules) : $rules;
    }

    /**
     * The validated terms that were sent, as columns: a percentage becomes percent_bp, hundredths of a percent (D27),
     * and times become instants, which AppTimezoneDateTime stores on the application clock (D13).
     *
     * @return array<string, mixed>
     */
    public function validatedTerms(): array
    {
        $validated = $this->validated();
        $terms = Arr::only($validated, ['name', 'amount', 'max_discount', 'min_rental', 'max_uses']);
        if (isset($validated['percent'])) {
            // At most two decimals, so rounding only removes the float error.
            $terms['percent_bp'] = (int) round((float) $validated['percent'] * 100);
        }
        foreach (['starts_at', 'ends_at'] as $field) {
            if (isset($validated[$field])) {
                $terms[$field] = CarbonImmutable::parse($validated[$field]);
            }
        }

        return $terms;
    }

    /** @return list<int>|null the court set when it was sent */
    public function validatedCourtIds(): ?array
    {
        $courtIds = $this->validated('court_ids');

        return $courtIds === null ? null : array_map('intval', $courtIds);
    }

    /** @return array<string, string> */
    protected function termMessages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên chương trình.',
            'name.string' => 'Tên chương trình không hợp lệ.',
            'name.max' => 'Tên chương trình tối đa 120 ký tự.',
            'percent.required' => 'Vui lòng nhập phần trăm giảm.',
            'percent.numeric' => 'Phần trăm giảm phải là số.',
            'percent.gt' => 'Phần trăm giảm phải lớn hơn 0.',
            'percent.lte' => 'Phần trăm giảm không được vượt quá 100.',
            'percent.decimal' => 'Phần trăm giảm có tối đa hai chữ số thập phân.',
            'percent.prohibited' => 'Chương trình giảm số tiền cố định không có phần trăm giảm.',
            'amount.required' => 'Vui lòng nhập số tiền giảm.',
            'amount.integer' => 'Số tiền giảm phải là số nguyên VND.',
            'amount.min' => 'Số tiền giảm phải lớn hơn 0.',
            'amount.max' => 'Số tiền giảm quá lớn.',
            'amount.prohibited' => 'Chương trình giảm theo phần trăm không có số tiền giảm cố định.',
            'max_discount.integer' => 'Mức giảm tối đa phải là số nguyên VND.',
            'max_discount.min' => 'Mức giảm tối đa phải lớn hơn 0.',
            'max_discount.max' => 'Mức giảm tối đa quá lớn.',
            'max_discount.prohibited' => 'Mức giảm tối đa chỉ dùng cho chương trình giảm theo phần trăm.',
            'min_rental.integer' => 'Tiền thuê tối thiểu phải là số nguyên VND.',
            'min_rental.min' => 'Tiền thuê tối thiểu phải lớn hơn 0.',
            'min_rental.max' => 'Tiền thuê tối thiểu quá lớn.',
            'starts_at.required' => 'Vui lòng chọn thời điểm bắt đầu.',
            'starts_at.date' => 'Thời điểm bắt đầu không hợp lệ.',
            'ends_at.required' => 'Vui lòng chọn thời điểm kết thúc.',
            'ends_at.date' => 'Thời điểm kết thúc không hợp lệ.',
            'ends_at.after' => 'Thời điểm kết thúc phải sau thời điểm bắt đầu.',
            'court_ids.required' => 'Vui lòng chọn ít nhất một sân.',
            'court_ids.min' => 'Vui lòng chọn ít nhất một sân.',
            'court_ids.list' => 'Danh sách sân không hợp lệ.',
            'court_ids.*' => 'Danh sách sân không hợp lệ.',
            'max_uses.required' => 'Vui lòng nhập tổng lượt sử dụng.',
            'max_uses.integer' => 'Tổng lượt sử dụng phải là số nguyên.',
            'max_uses.min' => 'Tổng lượt sử dụng phải lớn hơn 0.',
            'max_uses.max' => 'Tổng lượt sử dụng tối đa 1.000.000.',
            'max_uses.prohibited' => 'Chương trình riêng không có tổng lượt; số lượt là số mã.',
        ];
    }
}
