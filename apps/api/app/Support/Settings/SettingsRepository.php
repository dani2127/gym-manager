<?php

namespace App\Support\Settings;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Typed organization settings with optional per-branch overrides.
 * Resolution order: branch override → organization value → config default.
 */
class SettingsRepository
{
    /** @return array<string, array{rules: array<int, string>, default: mixed, organization_only?: bool}> */
    public function definitions(): array
    {
        return [
            'tax.enabled' => ['rules' => ['boolean'], 'default' => (bool) config('gym.tax.enabled')],
            'tax.name' => ['rules' => ['string', 'in:VAT,TOT'], 'default' => config('gym.tax.name')],
            'tax.rate' => ['rules' => ['numeric', 'min:0', 'max:100'], 'default' => (float) config('gym.tax.rate')],
            'tax.prices_inclusive' => ['rules' => ['boolean'], 'default' => (bool) config('gym.tax.prices_inclusive')],
            'freeze.enabled' => ['rules' => ['boolean'], 'default' => (bool) config('gym.features.freeze')],
            'lockers.enabled' => ['rules' => ['boolean'], 'default' => (bool) config('gym.features.lockers')],
            'chapa.enabled' => ['rules' => ['boolean'], 'default' => (bool) config('gym.features.chapa')],
            'classes.enabled' => ['rules' => ['boolean'], 'default' => true],
            // How many days ahead members can book, and how close to the start they can still cancel for free.
            'classes.booking_days' => ['rules' => ['integer', 'min:1', 'max:28'], 'default' => 7],
            'classes.cancel_cutoff_hours' => ['rules' => ['integer', 'min:0', 'max:48'], 'default' => 2],
            // Gym-wide only: branches can't override it.
            'security.require_two_factor' => ['rules' => ['boolean'], 'default' => false, 'organization_only' => true],
        ];
    }

    /**
     * Validation rules for a PATCH payload. Dotted keys are sent as-is in a
     * `settings` object, so they are addressed with escaped dots.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(bool $nullable = false): array
    {
        return collect($this->definitions())
            ->mapWithKeys(fn (array $definition, string $key) => [
                'settings.'.str_replace('.', '\.', $key) => [$nullable ? 'nullable' : 'sometimes', ...$definition['rules']],
            ])
            ->all();
    }

    /** @return array<string, mixed> Effective values for the organization or one of its branches. */
    public function all(Organization $organization, ?Branch $branch = null): array
    {
        $stored = Setting::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where(fn ($q) => $q->whereNull('branch_id')->when($branch, fn ($q) => $q->orWhere('branch_id', $branch->id)))
            ->get();

        $orgValues = $stored->whereNull('branch_id')->pluck('value', 'key');
        $branchValues = $branch ? $stored->where('branch_id', $branch->id)->pluck('value', 'key') : collect();

        return collect($this->definitions())
            ->map(fn (array $definition, string $key) => (empty($definition['organization_only']) ? $branchValues[$key] ?? null : null) ?? $orgValues[$key] ?? $definition['default'])
            ->all();
    }

    /** @return list<string> Keys the branch overrides. */
    public function overriddenKeys(Branch $branch): array
    {
        return Setting::withoutGlobalScopes()->where('branch_id', $branch->id)->pluck('key')->all();
    }

    public function get(string $key, Organization $organization, ?Branch $branch = null): mixed
    {
        return $this->all($organization, $branch)[$key] ?? null;
    }

    /**
     * Stores values; for branches a null value removes the override.
     *
     * @param  array<string, mixed>  $values
     */
    public function put(Organization $organization, array $values, ?Branch $branch = null): void
    {
        DB::transaction(function () use ($organization, $values, $branch) {
            foreach (array_intersect_key($values, $this->definitions()) as $key => $value) {
                if ($branch && ! empty($this->definitions()[$key]['organization_only'])) {
                    continue;
                }
                $query = Setting::withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('branch_id', $branch?->id)
                    ->where('key', $key)
                    ->lockForUpdate();

                if ($value === null) {
                    $query->delete();

                    continue;
                }

                $setting = $query->first() ?? new Setting([
                    'organization_id' => $organization->id,
                    'branch_id' => $branch?->id,
                    'key' => $key,
                ]);
                $setting->value = $value;
                $setting->save();
            }
        });
    }
}
