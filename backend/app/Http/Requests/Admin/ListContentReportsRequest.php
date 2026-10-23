<?php

namespace App\Http\Requests\Admin;

use App\Models\FeedReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListContentReportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in([FeedReport::STATUS_PENDING, FeedReport::STATUS_RESOLVED, 'all'])],
            'type' => ['nullable', Rule::in(array_keys(FeedReport::TYPES))],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
