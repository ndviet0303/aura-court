<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

#[Fillable(['venue_id', 'name', 'sport_type', 'slot_duration', 'description', 'is_active'])]
class SportZone extends Model
{
    use HasTranslations;

    public array $translatable = ['name', 'description'];

    protected function casts(): array
    {
        return [
            'slot_duration' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class);
    }

    public function priceRules(): HasMany
    {
        return $this->hasMany(PriceRule::class);
    }

    public function toArray()
    {
        $attributes = parent::toArray();

        if (request() && request()->is('api/*/admin/*')) {
            return $attributes;
        }

        foreach ($this->getTranslatableAttributes() as $field) {
            if (array_key_exists($field, $attributes)) {
                $translations = $this->getTranslations($field);
                $locale = app()->getLocale();
                $attributes[$field] = $translations[$locale]
                    ?? ($translations[config('app.fallback_locale')]
                    ?? ($translations['vi']
                    ?? ($translations['en']
                    ?? (is_array($translations) && count($translations) > 0 ? reset($translations) : ''))));
            }
        }

        return $attributes;
    }
}
