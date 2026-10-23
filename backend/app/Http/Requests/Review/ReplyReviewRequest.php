<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

/** FR-117: the owner's public reply, up to 2,000 characters. */
class ReplyReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:2000']];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Vui lòng nhập nội dung phản hồi.',
            'body.max' => 'Phản hồi tối đa 2.000 ký tự.',
        ];
    }
}
