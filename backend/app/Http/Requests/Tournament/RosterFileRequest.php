<?php

namespace App\Http\Requests\Tournament;

use Illuminate\Foundation\Http\FormRequest;

/** UC-7.27: the roster spreadsheet, built from the template. */
class RosterFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:xlsx', 'max:5120']];
    }

    public function messages(): array
    {
        return ['file.mimes' => 'Chỉ nhận tệp Excel .xlsx theo mẫu.', 'file.max' => 'Tệp tối đa 5 MB.'];
    }
}
