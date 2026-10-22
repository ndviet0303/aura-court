<?php

namespace App\Http\Requests\Partner;

use App\Models\OrganizationMember;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListOwnerFinanceTransactionsRequest extends FormRequest
{
    /** Owner of the path organization; a venue outside it is forbidden, whether or not it exists. */
    public function authorize(): bool
    {
        $organizationId = (int) $this->route('organizationId');
        // A non-integer id is left to rules() for a 422.
        $venueId = filter_var($this->input('venue_id'), FILTER_VALIDATE_INT);

        return $this->user()?->hasRoleInOrganization($organizationId, OrganizationMember::ROLE_ORGANIZATION_OWNER) === true
            && ($venueId === false || Venue::whereKey($venueId)->where('organization_id', $organizationId)->exists());
    }

    public function rules(): array
    {
        return [
            'venue_id' => ['nullable', 'integer'],
            // PostgreSQL has no year 0, so a far-past date must fail here rather than in the query.
            'from' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01'],
            // after_or_equal reads the raw from value, and an array from would throw a TypeError.
            'to' => ['required', 'date_format:Y-m-d', Rule::when(is_string($this->input('from')), 'after_or_equal:from')],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'venue_id.integer' => 'Cơ sở được chọn không hợp lệ.',
            'from.required' => 'Vui lòng chọn ngày bắt đầu của kỳ.',
            'from.date_format' => 'Ngày bắt đầu phải có dạng YYYY-MM-DD.',
            'from.after_or_equal' => 'Ngày bắt đầu không được trước ngày 01/01/2000.',
            'to.required' => 'Vui lòng chọn ngày kết thúc của kỳ.',
            'to.date_format' => 'Ngày kết thúc phải có dạng YYYY-MM-DD.',
            'to.after_or_equal' => 'Ngày kết thúc không được trước ngày bắt đầu.',
        ];
    }
}
