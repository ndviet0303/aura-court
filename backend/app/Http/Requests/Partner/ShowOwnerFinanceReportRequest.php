<?php

namespace App\Http\Requests\Partner;

use App\Models\Court;
use App\Models\OrganizationMember;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowOwnerFinanceReportRequest extends FormRequest
{
    /**
     * Owner of the path organization; a venue or court outside it is forbidden, whether or not it exists.
     * A court of the organization that is not in the chosen venue fails validation instead.
     */
    public function authorize(): bool
    {
        $organizationId = (int) $this->route('organizationId');
        // A non-integer id is left to rules() for a 422.
        $venueId = filter_var($this->input('venue_id'), FILTER_VALIDATE_INT);
        $courtId = filter_var($this->input('court_id'), FILTER_VALIDATE_INT);

        return $this->user()?->hasRoleInOrganization($organizationId, OrganizationMember::ROLE_ORGANIZATION_OWNER) === true
            && ($venueId === false || Venue::whereKey($venueId)->where('organization_id', $organizationId)->exists())
            && ($courtId === false || Court::whereKey($courtId)
                ->whereHas('venue', fn ($venues) => $venues->where('organization_id', $organizationId))
                ->exists());
    }

    public function rules(): array
    {
        return [
            'venue_id' => ['nullable', 'integer'],
            // bail: a non-numeric id must not reach the bigint lookup.
            'court_id' => ['bail', 'nullable', 'integer', Rule::exists('courts', 'id')
                ->when($this->integer('venue_id'), fn ($rule, int $venueId) => $rule->where('venue_id', $venueId))],
            // PostgreSQL has no year 0, so a far-past date must fail here rather than in the query.
            'from' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01'],
            // after_or_equal reads the raw from value, and an array from would throw a TypeError.
            'to' => ['required', 'date_format:Y-m-d', Rule::when(is_string($this->input('from')), 'after_or_equal:from')],
        ];
    }

    public function messages(): array
    {
        return [
            'venue_id.integer' => 'Cơ sở được chọn không hợp lệ.',
            'court_id.integer' => 'Sân được chọn không hợp lệ.',
            'court_id.exists' => 'Sân được chọn không thuộc cơ sở đã chọn.',
            'from.required' => 'Vui lòng chọn ngày bắt đầu của kỳ.',
            'from.date_format' => 'Ngày bắt đầu phải có dạng YYYY-MM-DD.',
            'from.after_or_equal' => 'Ngày bắt đầu không được trước ngày 01/01/2000.',
            'to.required' => 'Vui lòng chọn ngày kết thúc của kỳ.',
            'to.date_format' => 'Ngày kết thúc phải có dạng YYYY-MM-DD.',
            'to.after_or_equal' => 'Ngày kết thúc không được trước ngày bắt đầu.',
        ];
    }
}
