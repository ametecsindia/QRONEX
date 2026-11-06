<?php

namespace Tests\Unit;

use App\Models\PlanVersion;
use App\Services\PriceCalculator;
use PHPUnit\Framework\TestCase;

class PriceCalculatorTest extends TestCase
{
    private function growth(): PlanVersion
    {
        return (new PlanVersion)->forceFill([
            'pricing_model' => 'per_employee', 'per_employee_price' => 39, 'min_billing' => 1499,
            'quarterly_discount_pct' => 5, 'annual_discount_pct' => 15,
            'onboarding_fixed' => 4999, 'onboarding_per_employee' => 25,
        ]); // forceFill: no DB needed for a unit test
    }

    public function test_growth_40_employees_monthly_matches_prototype(): void
    {
        $q = (new PriceCalculator)->quote($this->growth(), 40, 'monthly', 18);

        $this->assertSame(1560, $q['subscription']);
        $this->assertSame(5999, $q['onboarding']);
        $this->assertSame(1361, $q['gst']);
        $this->assertSame(8920, $q['total']);
    }

    public function test_minimum_billing_and_annual_discount(): void
    {
        $q = (new PriceCalculator)->quote($this->growth(), 30, 'annual', 18); // 39*30=1170 < 1499 min

        $this->assertSame(1499, $q['base']);
        $this->assertSame((int) round(1499 * 12 * 0.85), $q['subscription']);
    }

    public function test_custom_plan_has_no_quote(): void
    {
        $this->assertNull((new PriceCalculator)->quote((new PlanVersion)->forceFill(['pricing_model' => 'custom']), 600, 'monthly', 18));
    }
}
