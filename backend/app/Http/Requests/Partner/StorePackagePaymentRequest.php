<?php

namespace App\Http\Requests\Partner;

use App\Domain\Package\Enums\PackageTerm;
use App\Models\OrganizationMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePackagePaymentRequest extends FormRequest
{
    /** Owner of the path organization; the action answers 404 for a venue outside it. */
    public function authorize(): bool
    {
        return $this->user()?->hasRoleInOrganization((int) $this->route('organizationId'), OrganizationMember::ROLE_ORGANIZATION_OWNER) === true;
    }

    public function rules(): array
    {
        return [
            'term_days' => ['required', 'integer', Rule::enum(PackageTerm::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'term_days.required' => 'Vui lòng chọn kỳ hạn gói.',
            'term_days.integer' => 'Kỳ hạn gói phải là 30, 90, 180 hoặc 360 ngày.',
            'term_days.enum' => 'Kỳ hạn gói phải là 30, 90, 180 hoặc 360 ngày.',
        ];
    }
}
