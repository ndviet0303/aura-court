<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class AttachPartnerDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'document_type' => [
                'required',
                'string',
                'in:representative_id_front,representative_id_back,business_license,business_registration_certificate,venue_operation_rights,bank_account_confirmation,power_of_attorney,brand_document,invoice_sample',
            ],
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB in KB
                'mimes:pdf,jpg,jpeg,png',
            ],
        ];
    }
}
