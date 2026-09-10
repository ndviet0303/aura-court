<?php

declare(strict_types=1);

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class TransitionVenueLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:published,temporarily_closed,closed,archived',
            'reason' => 'nullable|string|max:500',
        ];
    }
}
