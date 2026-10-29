<?php

namespace Tests\Unit;

use App\Billing\Money;
use App\Billing\TaxConfig;
use PHPUnit\Framework\TestCase;

class TaxConfigTest extends TestCase
{
    public function test_inclusive_vat_is_carved_out_of_the_price(): void
    {
        $vat = new TaxConfig(true, 'VAT', 1500, inclusive: true);

        $this->assertSame(['tax' => 15000, 'total' => 115000], $vat->apply(115000));
        // 2,500.00 incl. 15% → 326.09 VAT (326.086… rounds up).
        $this->assertSame(['tax' => 32609, 'total' => 250000], $vat->apply(250000));
    }

    public function test_exclusive_vat_is_added_on_top(): void
    {
        $vat = new TaxConfig(true, 'VAT', 1500, inclusive: false);

        $this->assertSame(['tax' => 37500, 'total' => 287500], $vat->apply(250000));
        $this->assertSame(['tax' => 0, 'total' => 0], $vat->apply(0));
    }

    public function test_tot_and_disabled_tax(): void
    {
        $this->assertSame(['tax' => 2000, 'total' => 102000], (new TaxConfig(true, 'TOT', 200, false))->apply(100000));
        $this->assertSame(['tax' => 0, 'total' => 250000], TaxConfig::none()->apply(250000));
    }

    public function test_rounding_is_half_up_on_santim(): void
    {
        $this->assertSame(3, Money::divideRounded(5, 2));
        $this->assertSame(2, Money::divideRounded(9, 4));
        $this->assertSame('1,150.00', Money::format(115000));
    }
}
