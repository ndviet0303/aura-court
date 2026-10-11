<?php

namespace App\Http\Requests\Booking;

use App\Domain\Booking\DTOs\CreateBookingData;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'court_id' => 'required|integer|exists:courts,id',
            'slots' => 'required|array|min:1|max:12',
            'slots.*' => 'date',
            'coupon_code' => 'nullable|string|max:40',
            'loyalty_points' => 'nullable|integer|min:1',
            'loyalty_policy_version' => 'nullable|integer',
            'idempotency_key' => 'nullable|string|max:100',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->sometimes('idempotency_key', 'required|string|max:100', function ($input) {
            $key = $this->header('Idempotency-Key') ?? $this->header('X-Idempotency-Key') ?? ($input->idempotency_key ?? null);

            return ! empty($input->loyalty_points) && empty($key);
        });
    }

    public function toData(): CreateBookingData
    {
        $data = $this->validated();
        if (empty($data['idempotency_key'])) {
            $data['idempotency_key'] = $this->header('Idempotency-Key') ?? $this->header('X-Idempotency-Key');
        }

        return CreateBookingData::fromArray($this->user()->id, $data);
    }
}
