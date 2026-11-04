<?php

namespace App\Http\Controllers\Api\V1\Catalog;

/** Validation for {"en": "...", "am": "..."} fields; English is required. */
trait TranslatedName
{
    /** @return array<string, array<int, string>> */
    protected function translatedRules(string $field, bool $required, int $max = 80): array
    {
        return [
            $field => [$required ? 'required' : 'sometimes', 'array:en,am'],
            "{$field}.en" => [$required ? 'required' : 'required_with:'.$field, 'string', "max:{$max}"],
            "{$field}.am" => ['nullable', 'string', "max:{$max}"],
        ];
    }
}
