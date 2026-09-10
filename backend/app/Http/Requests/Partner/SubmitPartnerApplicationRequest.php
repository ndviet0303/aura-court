<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPartnerApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'consents' => ['required', 'array', 'min:5'],
            'consents.*.consent_type' => [
                'required',
                'string',
                'in:cooperation_terms,platform_fee_policy,settlement_refund_terms,data_processing_agreement,service_responsibility',
            ],
            'consents.*.terms_version' => ['required', 'string', 'max:32'],
        ];
    }
}
