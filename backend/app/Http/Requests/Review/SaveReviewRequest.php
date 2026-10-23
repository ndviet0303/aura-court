<?php

namespace App\Http\Requests\Review;

use App\Rules\StoredImageUrl;
use Illuminate\Foundation\Http\FormRequest;

/** FR-116, BR-145: 1–5 stars, a comment up to 2,000 characters and up to 5 uploaded images; a new review also names the reservation. */
class SaveReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reservation_code' => [$this->isMethod('POST') ? 'required' : 'exclude', 'string', 'max:50'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:2000'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['string', new StoredImageUrl],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'Vui lòng chọn số sao.',
            'rating.between' => 'Số sao từ 1 đến 5.',
            'body.max' => 'Nhận xét tối đa 2.000 ký tự.',
            'images.max' => 'Đánh giá tối đa 5 ảnh.',
        ];
    }
}
