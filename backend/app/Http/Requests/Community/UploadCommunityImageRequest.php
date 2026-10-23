<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

/** D9: one image for a post or review, uploaded before the content is saved; a rejected file never reaches storage. */
class UploadCommunityImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // extensions checks the file name, mimes the type sniffed from the content; max is in kilobytes (5 MB).
            'image' => ['required', 'file', 'extensions:jpg,jpeg,png,webp', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Vui lòng chọn ảnh để tải lên.',
            'image.max' => 'Mỗi ảnh tối đa 5 MB.',
            'image' => 'Chỉ nhận ảnh JPG, PNG hoặc WebP.',
        ];
    }
}
