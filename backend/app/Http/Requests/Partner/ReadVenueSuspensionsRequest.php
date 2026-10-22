<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

/** D13: only active owners of the organization read its suspension decisions; staff, customers and others get 403. */
class ReadVenueSuspensionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ! $user->isPlatformAdmin() && $user->isOrganizationOwner((int) $this->route('organizationId'));
    }

    public function rules(): array
    {
        return [];
    }
}
