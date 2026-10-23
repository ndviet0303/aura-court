<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** BR-160: every hide, restore or keep decision carries the admin's note for the history. */
class ModerateContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['note' => ['required', 'string', 'max:1000']];
    }

    public function messages(): array
    {
        return [
            'note.required' => 'Vui lòng ghi chú lý do xử lý.',
            'note.max' => 'Ghi chú tối đa 1.000 ký tự.',
        ];
    }
}
