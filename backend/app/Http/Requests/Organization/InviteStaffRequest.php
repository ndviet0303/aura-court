<?php

namespace App\Http\Requests\Organization;

use App\Models\OrganizationMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** BR-17/20: the owner invites a staff member to at least one venue of the organization. */
class InviteStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRoleInOrganization($this->route('organization')->id, [OrganizationMember::ROLE_ORGANIZATION_OWNER]);
    }

    public function rules(): array
    {
        $venue = Rule::exists('venues', 'id')->where('organization_id', $this->route('organization')->id);

        return [
            'email' => ['required', 'email', 'max:254'],
            'venue_id' => ['nullable', 'integer', $venue],
            'venue_ids' => ['required_without:venue_id', 'array', 'min:1'],
            'venue_ids.*' => ['integer', $venue],
        ];
    }

    public function messages(): array
    {
        return [
            'venue_id.exists' => 'Cơ sở được chọn không thuộc tổ chức của bạn.',
            'venue_ids.*.exists' => 'Cơ sở được chọn không thuộc tổ chức của bạn.',
        ];
    }
}
