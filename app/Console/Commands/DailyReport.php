<?php

namespace App\Console\Commands;

use App\Models\AttendanceEvent;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\Notifier;
use Illuminate\Console\Command;

/** 07:00 IST: yesterday's verified punches as CSV to each company's HR address (Attendance policy → Notifications). */
class DailyReport extends Command
{
    protected $signature = 'qronex:daily-report {--date= : IST date, default yesterday}';

    protected $description = "Email yesterday's punch register to each company that asked for it";

    public function handle(): int
    {
        $day = \Illuminate\Support\Carbon::parse($this->option('date') ?? now('Asia/Kolkata')->subDay()->toDateString(), 'Asia/Kolkata');
        foreach (TenantSetting::withoutGlobalScopes()->whereNotNull('daily_report_email')->get() as $s) {
            $tenant = Tenant::find($s->tenant_id);
            if (! $tenant || $tenant->isBlocked()) {
                continue;
            }
            $rows = AttendanceEvent::withoutGlobalScopes()->with('employee', 'office', 'gate')->where('tenant_id', $tenant->id)
                ->whereBetween('recorded_at', [$day->copy()->startOfDay()->utc(), $day->copy()->endOfDay()->utc()])->orderBy('recorded_at')->get();
            $csv = fopen('php://temp', 'r+');
            fwrite($csv, "\xEF\xBB\xBF");
            fputcsv($csv, ['ref', 'employee_code', 'employee_name', 'type', 'time_ist', 'mode', 'office', 'gate', 'adjusted']);
            foreach ($rows as $e) {
                fputcsv($csv, [$e->ref, $e->employee?->employee_code, $e->employee?->name, $e->type, $e->recorded_at->timezone('Asia/Kolkata')->format('H:i:s'), $e->mode, $e->office?->name, $e->gate?->name, $e->adjusted ? 'yes' : '']);
            }
            rewind($csv);
            Notifier::email('daily_report', $s->daily_report_email, [
                'company_name' => $tenant->name, 'date' => $day->format('d M Y'), 'punches' => $rows->count(), 'employees' => $rows->pluck('employee_id')->unique()->count(),
            ], $tenant->id, [['name' => "qronex-register-{$day->format('Ymd')}.csv", 'data' => stream_get_contents($csv), 'mime' => 'text/csv']]);
            $this->line("{$tenant->slug}: {$rows->count()} punches → {$s->daily_report_email}");
        }

        return self::SUCCESS;
    }
}
