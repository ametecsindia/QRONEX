<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Subscription;
use App\Services\Notifier;
use Illuminate\Console\Command;

/**
 * Daily lifecycle: trial reminder (3 days left) → trial/period ended → grace → after grace_days → suspended.
 * Complimentary arrangements never lapse. Idempotent: only moves subscriptions forward.
 */
class BillingSweep extends Command
{
    protected $signature = 'qronex:billing-sweep';

    protected $description = 'Advance trial / grace / suspended states and send reminders';

    public function handle(): int
    {
        $grace = config('qronex.grace_days');
        $moved = 0;

        Subscription::withoutGlobalScopes()->with('planVersion.plan')->whereIn('status', ['trial', 'active', 'grace'])
            ->where('arrangement', '!=', 'complimentary')->chunkById(200, function ($subs) use ($grace, &$moved) {
                foreach ($subs as $s) {
                    $tenant = \App\Models\Tenant::find($s->tenant_id);
                    if (! $tenant) {
                        continue;
                    }
                    $ends = $s->status === 'trial' ? $s->trial_ends_at : $s->current_period_end;
                    $owner = $tenant->owner;
                    $vars = ['company_name' => $tenant->name, 'admin_name' => $owner?->name, 'subscription_plan' => $s->planVersion?->plan?->name];

                    if ($s->status === 'trial' && $ends && now()->diffInDays($ends, false) > 2 && now()->diffInDays($ends, false) <= 3) {
                        Notifier::email('trial', $owner?->email, $vars + ['days_left' => 3], $tenant->id);
                    }
                    if (! $ends || $ends->isFuture()) {
                        continue;
                    }

                    $to = $ends->copy()->addDays($grace)->isPast() ? 'suspended' : 'grace';
                    if ($to === $s->status) {
                        continue;
                    }
                    $s->update(['status' => $to]);
                    $tenant->update(['status' => $to]);
                    AuditLog::create(['tenant_id' => $tenant->id, 'action' => "billing.$to", 'subject' => $tenant->slug]);
                    Notifier::email('renewal', $owner?->email, $vars + ['renewal_date' => $ends->timezone('Asia/Kolkata')->format('d M Y'), 'amount' => 'your plan amount'], $tenant->id);
                    $moved++;
                }
            });

        $this->info("Billing sweep done: $moved subscription(s) changed state.");

        return self::SUCCESS;
    }
}
