<?php

namespace App\Http\Requests\Community;

use App\Models\FeedReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** FR-132, BR-160: a report names a post, comment or review and says why. */
class ReportContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(FeedReport::TYPES))],
            'id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Vui lòng nhập lý do báo cáo.',
            'reason.max' => 'Lý do tối đa 500 ký tự.',
        ];
    }
}
