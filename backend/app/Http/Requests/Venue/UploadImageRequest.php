<?php

namespace App\Http\Requests\Venue;

use Illuminate\Foundation\Http\FormRequest;

/** D11: a venue or court image upload; a rejected file never reaches storage. */
class UploadImageRequest extends FormRequest
{
    /** Only owners add venue and court images; staff, also admitted by venue_admin, have read-only venue access. */
    public function authorize(): bool
    {
        return (bool) $this->user()?->isOrganizationOwner();
    }

    public function rules(): array
    {
        return [
            // extensions checks the file name, mimes the type sniffed from the content; max is in kilobytes (5 MB).
            'image' => ['required', 'file', 'extensions:jpg,jpeg,png', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Vui lòng chọn ảnh để tải lên.',
            'image.max' => 'Mỗi ảnh tối đa 5 MB.',
            'image' => 'Chỉ nhận ảnh JPG, JPEG hoặc PNG.',
        ];
    }
}
