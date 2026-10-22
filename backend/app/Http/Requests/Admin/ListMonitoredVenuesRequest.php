<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ListMonitoredVenuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:inactive,active,paused,closed'],
            'suspended' => ['nullable', 'in:0,1,true,false'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** Typed filters without empty values; `suspended` stays a real bool so "false" filters unsuspended venues. */
    public function filters(): array
    {
        $filters = array_filter($this->validated(), fn ($value) => $value !== null && $value !== '');
        foreach (['organization_id', 'per_page'] as $key) {
            if (isset($filters[$key])) {
                $filters[$key] = (int) $filters[$key];
            }
        }
        if (array_key_exists('suspended', $filters)) {
            $filters['suspended'] = $this->boolean('suspended');
        }

        return $filters;
    }
}
