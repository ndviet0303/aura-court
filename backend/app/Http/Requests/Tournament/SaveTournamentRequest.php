<?php

namespace App\Http\Requests\Tournament;

use App\Domain\Tournament\Rules\SetupRules;
use App\Models\Tournament;
use Illuminate\Foundation\Http\FormRequest;

/** FR-120, BR-149: a new public tournament for one of the owner's venues, or a draft's changed setup. */
class SaveTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tournament = $this->route('tournament');

        return $tournament instanceof Tournament
            ? SetupRules::rules($tournament->isPublic())
            : SetupRules::rules(true) + ['venue_id' => ['required', 'integer', 'min:1']];
    }

    public function messages(): array
    {
        return SetupRules::messages();
    }

    public function isPublic(): bool
    {
        $tournament = $this->route('tournament');

        return ! $tournament instanceof Tournament || $tournament->isPublic();
    }
}
