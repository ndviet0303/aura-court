<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class SavePartnerApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'business_type' => ['nullable', 'string', 'in:individual,household,company'],
            'business_name' => ['nullable', 'string', 'max:190'],
            'legal_name' => ['nullable', 'string', 'max:190'],
            'tax_code' => ['nullable', 'string', 'max:64'],
            'tax_status' => ['nullable', 'string', 'max:64'],
            'representative_name' => ['nullable', 'string', 'max:120'],
            'id_card_number' => ['nullable', 'string', 'max:64'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_code' => ['nullable', 'string', 'max:32'],
            'bank_account_number' => ['nullable', 'string', 'max:64'],
            'bank_account_holder' => ['nullable', 'string', 'max:120'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'string', 'email', 'max:190'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'sports' => ['nullable', 'array'],
            'sports.*' => ['string', 'max:50'],
            'court_scale' => ['nullable', 'integer', 'min:1', 'max:100'],
            'lighting_spec' => ['nullable', 'string', 'max:120'],
            'expected_version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
