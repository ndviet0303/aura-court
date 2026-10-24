<?php

namespace App\Http\Requests\Tournament;

use Illuminate\Foundation\Http\FormRequest;

/** A reason the history keeps: required to reject an application (FR-125), optional to stop a tournament (FR-131). */
class ReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reason' => [$this->routeIs('tournament-entries.reject') ? 'required' : 'nullable', 'string', 'max:1000']];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Vui lòng nhập lý do.'];
    }
}
