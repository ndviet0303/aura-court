<?php

namespace App\Http\Requests\Community;

use App\Models\Court;
use App\Models\FeedPost;
use App\Rules\StoredImageUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** FR-113, BR-145: up to 5,000 characters and 10 uploaded images, on create and on edit alike. */
class SavePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $matchmaking = 'required_if:type,'.FeedPost::TYPE_MATCHMAKING;

        return [
            'type' => ['required', Rule::in([FeedPost::TYPE_SHARE, FeedPost::TYPE_MATCHMAKING])],
            'content' => ['required', 'string', 'max:5000'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['string', new StoredImageUrl],
            'sport' => ['nullable', $matchmaking, Rule::in(Court::SPORT_TYPES)],
            'venue_id' => ['nullable', Rule::requiredIf(fn () => $this->input('type') === FeedPost::TYPE_MATCHMAKING && blank($this->input('address'))), 'integer', 'exists:venues,id'],
            'address' => ['nullable', 'string', 'max:255'],
            'court_code' => ['nullable', 'string', 'max:50'],
            'play_at' => ['nullable', $matchmaking, 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Vui lòng nhập nội dung bài viết.',
            'content.max' => 'Bài viết tối đa 5.000 ký tự.',
            'images.max' => 'Bài viết tối đa 10 ảnh.',
            'sport.required_if' => 'Bài giao lưu cần chọn môn thể thao.',
            'venue_id.required' => 'Bài giao lưu cần chọn cơ sở hoặc nhập địa chỉ sân.',
            'play_at.required_if' => 'Bài giao lưu cần ngày và giờ chơi.',
        ];
    }
}
