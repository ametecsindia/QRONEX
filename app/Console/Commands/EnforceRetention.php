<?php

namespace App\Console\Commands;

use App\Models\AttendanceEvent;
use App\Models\AttendanceRejection;
use App\Models\AuditLog;
use App\Models\TenantSetting;
use Illuminate\Console\Command;

/** Deletes attendance older than each company's retention setting (Attendance policy → Record retention). */
class EnforceRetention extends Command
{
    protected $signature = 'qronex:retention {--dry-run : Count only}';

    protected $description = 'Delete attendance records older than each company\'s retention period';

    public function handle(): int
    {
        foreach (TenantSetting::withoutGlobalScopes()->get() as $s) {
            $cutoff = now()->subMonthsNoOverflow(max(6, (int) $s->retention_months));
            $events = AttendanceEvent::withoutGlobalScopes()->where('tenant_id', $s->tenant_id)->where('recorded_at', '<', $cutoff);
            $rejections = AttendanceRejection::withoutGlobalScopes()->where('tenant_id', $s->tenant_id)->where('created_at', '<', $cutoff);
            [$e, $r] = [(clone $events)->count(), (clone $rejections)->count()];
            if (! $e && ! $r) {
                continue;
            }
            if (! $this->option('dry-run')) {
                $events->delete();
                $rejections->delete();
                AuditLog::create(['tenant_id' => $s->tenant_id, 'action' => 'retention.purged', 'subject' => "before {$cutoff->toDateString()}", 'meta' => ['events' => $e, 'rejections' => $r]]);
            }
            $this->line("tenant {$s->tenant_id}: {$e} events, {$r} rejections ".($this->option('dry-run') ? 'would be deleted' : 'deleted'));
        }

        return self::SUCCESS;
    }
}
