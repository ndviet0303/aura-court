<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** One athlete of an entry: full name, date of birth and the tournament's extra athlete fields (FR-124, BR-150). */
#[Fillable(['entry_id', 'full_name', 'birth_date', 'extra', 'role', 'position'])]
class TournamentEntryMember extends Model
{
    public const ROLE_MAIN = 'main';

    public const ROLE_RESERVE = 'reserve';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['birth_date' => 'date:Y-m-d', 'extra' => 'array', 'position' => 'integer'];
    }
}
