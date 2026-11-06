<?php

namespace App\Services;

use App\Models\PlanVersion;
use App\Models\PlatformSetting;

/** Same maths as the Stage 1 prototype quote(). Amounts in whole rupees. */
class PriceCalculator
{
    public const MONTHS = ['monthly' => 1, 'quarterly' => 3, 'annual' => 12];

    /** @return array{base:int,months:int,discount_pct:float,subscription:int,onboarding:int,taxable:int,gst_pct:float,gst:int,total:int}|null null = custom/Enterprise */
    public function quote(PlanVersion $v, int $employees, string $cycle = 'monthly', ?float $gstPct = null): ?array
    {
        if ($v->pricing_model === 'custom') {
            return null;
        }

        $months = self::MONTHS[$cycle] ?? throw new \InvalidArgumentException("Unknown billing cycle: $cycle");
        $disc = match ($cycle) {
            'quarterly' => (float) $v->quarterly_discount_pct,
            'annual' => (float) $v->annual_discount_pct,
            default => 0.0,
        };
        $gstPct ??= (float) PlatformSetting::get('gst_pct', 18);

        $base = $v->pricing_model === 'slab'
            ? (float) $v->slab_price
            : max((float) $v->per_employee_price * $employees, (float) $v->min_billing);

        $sub = (int) round($base * $months * (1 - $disc / 100));
        $onboard = (int) round((float) $v->onboarding_fixed + (float) $v->onboarding_per_employee * $employees);
        $taxable = $sub + $onboard;
        $gst = (int) round($taxable * $gstPct / 100);

        return [
            'base' => (int) round($base), 'months' => $months, 'discount_pct' => $disc,
            'subscription' => $sub, 'onboarding' => $onboard, 'taxable' => $taxable,
            'gst_pct' => $gstPct, 'gst' => $gst, 'total' => $taxable + $gst,
        ];
    }
}
