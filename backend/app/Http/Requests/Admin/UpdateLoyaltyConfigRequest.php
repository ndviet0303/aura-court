<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoyaltyConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'spendAmountVnd' => 'nullable|integer|min:1',
            'pointsEarned' => 'nullable|integer|min:1',
            'pointsRedeemed' => 'nullable|integer|min:1',
            'rewardValueVnd' => 'nullable|integer|min:1',
        ];
    }
}
