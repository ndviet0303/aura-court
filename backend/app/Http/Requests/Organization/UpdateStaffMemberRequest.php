<?php

namespace App\Http\Requests\Organization;

use App\Models\OrganizationMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** BR-20/22: the owner sets a staff member's status and venues; an empty venue list removes all venue access. */
class UpdateStaffMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRoleInOrganization($this->route('organization')->id, [OrganizationMember::ROLE_ORGANIZATION_OWNER]);
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:active,suspended'],
            'venue_ids' => ['sometimes', 'present', 'array'],
            'venue_ids.*' => ['integer', Rule::exists('venues', 'id')->where('organization_id', $this->route('organization')->id)],
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
