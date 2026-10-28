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
            // UC-5.4 A-2, UC-5.8 E-1: the price the owner confirmed; a changed price is shown again instead of charged.
            'expected_amount' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'term_days.required' => 'Vui lòng chọn kỳ hạn gói.',
            'term_days.integer' => 'Kỳ hạn gói phải là 30, 90, 180 hoặc 360 ngày.',
            'term_days.enum' => 'Kỳ hạn gói phải là 30, 90, 180 hoặc 360 ngày.',
            'expected_amount.required' => 'Vui lòng xác nhận giá gói trước khi đăng ký.',
            'expected_amount.integer' => 'Giá gói phải là số nguyên VND lớn hơn 0.',
            'expected_amount.min' => 'Giá gói phải là số nguyên VND lớn hơn 0.',
        ];
    }
}
