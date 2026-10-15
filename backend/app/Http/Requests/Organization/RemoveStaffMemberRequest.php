<?php

namespace App\Http\Requests\Organization;

use App\Models\OrganizationMember;
use Illuminate\Foundation\Http\FormRequest;

/** BR-22: only the owner ends a staff membership. */
class RemoveStaffMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRoleInOrganization($this->route('organization')->id, [OrganizationMember::ROLE_ORGANIZATION_OWNER]);
    }

    public function rules(): array
    {
        return [];
    }
}
