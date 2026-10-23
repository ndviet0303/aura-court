<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

/** FR-114, BR-145: a comment of up to 2,000 characters, on create and on edit alike. */
class SaveCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['content' => ['required', 'string', 'max:2000']];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Vui lòng nhập nội dung bình luận.',
            'content.max' => 'Bình luận tối đa 2.000 ký tự.',
        ];
    }
}
