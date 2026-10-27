<?php

namespace App\Models\Concerns;

/**
 * Columns stored as {"en": "...", "am": "..."}. English is required; Amharic
 * falls back to English when it is missing.
 */
trait HasTranslations
{
    public function translate(string $attribute, ?string $locale = null): string
    {
        $values = $this->getAttribute($attribute) ?? [];
        $locale ??= app()->getLocale();

        return (string) (($values[$locale] ?? null) ?: ($values['en'] ?? ''));
    }
}
