<?php

namespace App\Http\Requests\Promotion;

use App\Models\PromotionProgram;
use App\Traits\AuthorizesPromotionVenue;
use App\Traits\ValidatesPromotionTerms;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Changes to an issued program (design D14); every field is optional. The voucher kind, discount kind, codes and
 * code count are fixed from the issue: the same value is accepted, another is refused with 422.
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

        return [
            'type' => ['sometimes', Rule::in([$program->type])],
            'discount_kind' => ['sometimes', Rule::in([$program->discount_kind])],
            'code_count' => ['sometimes', $program->code_count === null ? 'prohibited' : Rule::in([$program->code_count])],
            'codes' => ['prohibited'],
            ...$this->termRules($program->discount_kind, $program->type, true),
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Không đổi được loại voucher của chương trình đã phát hành.',
            'discount_kind.in' => 'Không đổi được hình thức giảm của chương trình đã phát hành.',
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
