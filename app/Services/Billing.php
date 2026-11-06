<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PlanVersion;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Billing
{
    public function __construct(private PriceCalculator $calc) {}

    /** Published quote, adjusted by the tenant's commercial arrangement. Onboarding is charged only on the first paid invoice. */
    public function quote(Tenant $tenant, PlanVersion $v, int $employees, string $cycle): ?array
    {
        $q = $this->calc->quote($v, $employees, $cycle);
        if (! $q) {
            return null;
        }
        $sub = $tenant->subscription;
        $months = PriceCalculator::MONTHS[$cycle];

        $q['subscription'] = match ($sub?->arrangement) {
            'complimentary' => 0,
            'discount' => (int) round($q['subscription'] * (1 - $sub->discount_pct / 100)),
            'custom' => (int) round($sub->custom_monthly * $months),
            default => $q['subscription'],
        };
        if ($sub?->arrangement === 'complimentary' || Invoice::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', 'paid')->exists()) {
            $q['onboarding'] = 0;
        }
        $q['taxable'] = $q['subscription'] + $q['onboarding'];
        $q['gst'] = (int) round($q['taxable'] * $q['gst_pct'] / 100);
        $q['total'] = $q['taxable'] + $q['gst'];
        $q['arrangement'] = $sub?->arrangement ?? 'published';

        return $q;
    }

    /** Intra-state supply (same state as supplier) → CGST + SGST halves; otherwise IGST. Unknown state is treated as intra-state. */
    public static function gstSplit(float $gst, ?string $customerState, string $supplierState): array
    {
        $intra = ! $customerState || strcasecmp(trim($customerState), trim($supplierState)) === 0;
        $half = round($gst / 2, 2);

        return $intra ? ['cgst' => $half, 'sgst' => round($gst - $half, 2), 'igst' => 0.0] : ['cgst' => 0.0, 'sgst' => 0.0, 'igst' => round($gst, 2)];
    }

    /** Indian financial year label, e.g. 5 Nov 2026 → "26-27". */
    public static function financialYear(Carbon $d): string
    {
        $start = $d->month >= 4 ? $d->year : $d->year - 1;

        return sprintf('%02d-%02d', $start % 100, ($start + 1) % 100);
    }

    /** Idempotent: safe to call from both the checkout callback and the webhook. */
    public function markPaid(Payment $payment, string $paymentId): Payment
    {
        return DB::transaction(function () use ($payment, $paymentId) {
            $p = Payment::withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->id);
            if ($p->status === 'paid') {
                return $p;
            }

            $tenant = Tenant::findOrFail($p->tenant_id);
            $m = $p->meta;
            $q = $m['quote'];
            $v = PlanVersion::with('plan')->findOrFail($m['plan_version_id']);
            $months = PriceCalculator::MONTHS[$m['cycle']];

            $sub = Subscription::withoutGlobalScopes()->where('tenant_id', $tenant->id)->latest('id')->first();
            $start = $sub && $sub->status === 'active' && $sub->current_period_end?->isFuture() ? $sub->current_period_end->copy() : today();
            $attrs = [
                'plan_version_id' => $v->id, 'billing_cycle' => $m['cycle'], 'employee_count' => $m['employees'], 'status' => 'active',
                'trial_ends_at' => null, 'current_period_start' => $start->toDateString(), 'current_period_end' => $start->copy()->addMonthsNoOverflow($months)->toDateString(),
            ];
            $sub ? $sub->update($attrs) : $sub = Subscription::create($attrs + ['tenant_id' => $tenant->id]);

            $fy = self::financialYear(now('Asia/Kolkata'));
            $prefix = config('qronex.invoice_prefix')."/$fy/";
            // Highest existing number + 1 (fixed-width, so string order = numeric order), under a row lock.
            // ponytail: gap-free per-FY sequence; move to a counters table if this lock ever becomes a hotspot.
            $last = Invoice::withoutGlobalScopes()->where('number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('number')->value('number');
            $seq = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

            $invoice = Invoice::create([
                'tenant_id' => $tenant->id, 'subscription_id' => $sub->id, 'number' => $prefix.sprintf('%04d', $seq), 'issued_on' => today(),
                'description' => "{$v->plan->name} plan · {$m['cycle']} · {$m['employees']} employees",
                'subtotal' => $q['subscription'], 'onboarding' => $q['onboarding'], 'taxable' => $q['taxable'], 'gst_rate' => $q['gst_pct'],
                ...self::gstSplit($q['gst'], $tenant->state, config('qronex.supplier.state')),
                'total' => $q['total'], 'status' => 'paid', 'payment_ref' => $paymentId,
            ]);

            $p->update(['status' => 'paid', 'payment_id' => $paymentId, 'invoice_id' => $invoice->id]);
            $tenant->update(['status' => 'active']);
            AuditLog::create(['tenant_id' => $tenant->id, 'action' => 'billing.paid', 'subject' => $invoice->number, 'meta' => ['payment' => $paymentId, 'total' => $q['total']]]);

            $owner = $tenant->owner;
            DB::afterCommit(function () use ($owner, $tenant, $invoice, $v) {
                $vars = ['company_name' => $tenant->name, 'admin_name' => $owner?->name, 'invoice_number' => $invoice->number,
                    'amount' => '₹'.number_format($invoice->total, 2), 'subscription_plan' => $v->plan->name];
                Notifier::email('pay_ok', $owner?->email, $vars, $tenant->id);
                Notifier::email('invoice', $owner?->email, $vars, $tenant->id);
            });

            return $p->fresh();
        });
    }

    public function markFailed(Payment $payment, ?string $reason): void
    {
        if ($payment->status !== 'created') {
            return;
        }
        $payment->update(['status' => 'failed', 'failure_reason' => $reason ? mb_substr($reason, 0, 250) : null]);
        $tenant = Tenant::find($payment->tenant_id);
        AuditLog::create(['tenant_id' => $tenant->id, 'action' => 'billing.failed', 'subject' => $payment->order_id, 'meta' => ['reason' => $reason]]);
        Notifier::email('pay_fail', $tenant->owner?->email, ['company_name' => $tenant->name, 'amount' => '₹'.number_format($payment->rupees(), 2)], $tenant->id);
    }

    public static function gstPct(): float
    {
        return (float) PlatformSetting::get('gst_pct', 18);
    }
}
