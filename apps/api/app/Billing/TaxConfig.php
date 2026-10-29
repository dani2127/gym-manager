<?php

namespace App\Billing;

use App\Models\Branch;
use App\Support\Settings\SettingsRepository;

/**
 * The tax a branch charges: VAT or TOT at a rate, with prices quoted either
 * including tax (the tax is carved out of the price) or excluding it (the
 * tax is added on top). Read from the branch's effective settings.
 */
final readonly class TaxConfig
{
    public function __construct(
        public bool $enabled,
        public string $name,
        /** Rate in basis points: 15% = 1500. */
        public int $rateBp,
        public bool $inclusive,
    ) {}

    public static function forBranch(Branch $branch): self
    {
        $settings = app(SettingsRepository::class)->all($branch->organization, $branch);

        return new self(
            enabled: (bool) $settings['tax.enabled'] && (float) $settings['tax.rate'] > 0,
            name: (string) $settings['tax.name'],
            rateBp: (int) round((float) $settings['tax.rate'] * 100),
            inclusive: (bool) $settings['tax.prices_inclusive'],
        );
    }

    public static function none(): self
    {
        return new self(false, 'VAT', 0, true);
    }

    /**
     * Tax on a line whose price after discount is $net santim.
     *
     * @return array{tax: int, total: int}
     */
    public function apply(int $net): array
    {
        if (! $this->enabled) {
            return ['tax' => 0, 'total' => $net];
        }

        if ($this->inclusive) {
            // 1,150.00 incl. 15% → tax 150.00, total stays 1,150.00.
            $tax = Money::divideRounded($net * $this->rateBp, 10000 + $this->rateBp);

            return ['tax' => $tax, 'total' => $net];
        }

        $tax = Money::divideRounded($net * $this->rateBp, 10000);

        return ['tax' => $tax, 'total' => $net + $tax];
    }

    public function ratePercent(): float
    {
        return $this->enabled ? $this->rateBp / 100 : 0.0;
    }
}
