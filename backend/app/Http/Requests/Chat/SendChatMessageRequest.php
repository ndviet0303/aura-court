<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

/** FR-118, BR-145: text up to 2,000 characters and/or one JPG, PNG or WebP image up to 5 MB. */
class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'required_without:image', 'string', 'max:2000'],
            // extensions checks the file name, mimes the type sniffed from the content; max is in kilobytes.
            'image' => ['nullable', 'file', 'extensions:jpg,jpeg,png,webp', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required_without' => 'Vui lòng nhập nội dung hoặc chọn ảnh.',
            'body.max' => 'Tin nhắn tối đa 2.000 ký tự.',
            'image.max' => 'Ảnh tối đa 5 MB.',
            'image' => 'Chỉ nhận ảnh JPG, PNG hoặc WebP.',
        ];
    }
}
