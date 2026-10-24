<?php

namespace App\Http\Requests\Tournament;

use App\Models\TournamentGrant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** UC-7.18: the account, by email, that gets the official or representative role. */
class GrantRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in([TournamentGrant::ROLE_OFFICIAL, TournamentGrant::ROLE_REPRESENTATIVE])],
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
