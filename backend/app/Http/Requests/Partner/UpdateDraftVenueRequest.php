<?php

declare(strict_types=1);

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDraftVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:500',
            'city' => 'sometimes|nullable|string|max:100',
            'district' => 'sometimes|nullable|string|max:100',
            'ward' => 'sometimes|nullable|string|max:100',
            'phone' => 'sometimes|nullable|string|max:30',
            'email' => 'sometimes|nullable|email|max:255',
            'description' => 'sometimes|nullable|string|max:2000',
            'images' => 'sometimes|nullable|array',
            'images.*' => 'url|max:2048',
            'google_maps_url' => 'nullable|url|max:2048',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string|max:50',
        ];
    }
}
