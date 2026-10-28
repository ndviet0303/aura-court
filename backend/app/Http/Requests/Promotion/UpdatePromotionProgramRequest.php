<?php

namespace App\Http\Requests\Promotion;

use App\Models\PromotionProgram;
use App\Traits\AuthorizesPromotionVenue;
use App\Traits\ValidatesPromotionTerms;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Changes to an issued program; every field is optional. The discount kind may change (UC-3.12 step 3): the new
 * kind's value is then required and the action clears the old kind's terms. The voucher kind, codes and code count
 * are fixed from the issue: the same value is accepted, another is refused with 422.
 */
class UpdatePromotionProgramRequest extends FormRequest
{
    use AuthorizesPromotionVenue;
    use ValidatesPromotionTerms;

    public function authorize(): bool
    {
        return $this->managesPromotionVenue($this, $this->program()->venue);
    }

    public function rules(): array
    {
        $program = $this->program();
        $kind = $this->discountKind();
        $rules = [
            'type' => ['sometimes', Rule::in([$program->type])],
            'discount_kind' => ['sometimes', Rule::in([PromotionProgram::KIND_PERCENT, PromotionProgram::KIND_FIXED])],
            'code_count' => ['sometimes', $program->code_count === null ? 'prohibited' : Rule::in([$program->code_count])],
            'codes' => ['prohibited'],
            ...$this->termRules($kind, $program->type, true),
        ];
        if ($kind !== $program->discount_kind) {
            $rules[$kind === PromotionProgram::KIND_PERCENT ? 'percent' : 'amount'][0] = 'required';
        }

        return $rules;
    }

    /** The discount kind after the update: a valid one sent, else the program's. */
    public function discountKind(): string
    {
        $kind = $this->input('discount_kind');

        return in_array($kind, [PromotionProgram::KIND_PERCENT, PromotionProgram::KIND_FIXED], true)
            ? $kind
            : $this->program()->discount_kind;
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Không đổi được loại voucher của chương trình đã phát hành.',
            'discount_kind.in' => 'Hình thức giảm không hợp lệ.',
            'code_count.in' => 'Không đổi được số mã của chương trình đã phát hành.',
            'code_count.prohibited' => 'Không đổi được số mã của chương trình đã phát hành.',
            'codes.prohibited' => 'Không đổi được mã của chương trình đã phát hành.',
            ...$this->termMessages(),
        ];
    }

    public function program(): PromotionProgram
    {
        /** @var PromotionProgram $program */
        $program = $this->route('program');

        return $program;
    }
}
