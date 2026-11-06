<?php

namespace App\Services;

use App\Models\AttendanceEvent;
use App\Models\AttendanceRejection;
use App\Models\Employee;
use App\Models\TenantSetting;
use Carbon\CarbonImmutable;

/**
 * Dashboard numbers. Day buckets are computed in PHP (IST) so the same code runs on MySQL and SQLite.
 * ponytail: pulls first-IN rows for the window; fine to ~50k employees×14 days. Pre-aggregate a daily table beyond that.
 */
class Analytics
{
    public const TZ = 'Asia/Kolkata';

    /** Company dashboard for one tenant, optionally limited to some offices. */
    public static function company(int $tenantId, ?array $officeIds, int $days = 14): array
    {
        $s = TenantSetting::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();
        [$h, $m] = array_map('intval', explode(':', $s?->shift_start ?: '09:30'));
        $lateAfter = $h * 60 + $m + (int) ($s?->late_after_min ?? 10);

        $today = CarbonImmutable::now(self::TZ)->startOfDay();
        $from = $today->subDays($days - 1);
        $scope = fn ($q) => $q->where('tenant_id', $tenantId)->when($officeIds !== null, fn ($q) => $q->whereIn('office_id', $officeIds));

        $employees = Employee::withoutGlobalScopes()->tap($scope)->where('status', 'active')->get(['id', 'office_id']);
        $ins = AttendanceEvent::withoutGlobalScopes()->tap($scope)->where('type', 'IN')->where('recorded_at', '>=', $from->utc())
            ->orderBy('recorded_at')->get(['employee_id', 'office_id', 'recorded_at']);

        // first IN per employee per local day
        $first = [];
        foreach ($ins as $e) {
            $local = $e->recorded_at->copy()->timezone(self::TZ);
            $first[$local->toDateString()][$e->employee_id] ??= ['min' => $local->hour * 60 + $local->minute, 'office' => $e->office_id];
        }

        $trend = ['labels' => [], 'on_time' => [], 'late' => []];
        for ($d = $from; $d <= $today; $d = $d->addDay()) {
            $rows = $first[$d->toDateString()] ?? [];
            $late = count(array_filter($rows, fn ($r) => $r['min'] > $lateAfter));
            $trend['labels'][] = $d->format('d M');
            $trend['late'][] = $late;
            $trend['on_time'][] = count($rows) - $late;
        }

        $todayRows = $first[$today->toDateString()] ?? [];
        $present = count($todayRows);
        $late = count(array_filter($todayRows, fn ($r) => $r['min'] > $lateAfter));
        $remote = \App\Models\RemotePermission::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('status', 'approved')
            ->whereDate('from_date', '<=', $today->toDateString())->whereDate('to_date', '>=', $today->toDateString())
            ->when($officeIds !== null, fn ($q) => $q->whereIn('employee_id', $employees->pluck('id')))->count();

        // arrival time distribution over the window (first IN), 30-minute buckets 07:00–12:00+
        $buckets = [];
        foreach (range(7 * 60, 11 * 60 + 30, 30) as $b) {
            $buckets[sprintf('%02d:%02d', intdiv($b, 60), $b % 60)] = 0;
        }
        $buckets['12:00+'] = 0;
        $early = 0;
        foreach ($first as $rows) {
            foreach ($rows as $r) {
                if ($r['min'] < 7 * 60) { $early++; continue; }
                $k = $r['min'] >= 12 * 60 ? '12:00+' : sprintf('%02d:%02d', intdiv($r['min'], 60), $r['min'] % 60 < 30 ? 0 : 30);
                $buckets[$k]++;
            }
        }

        $byOffice = [];
        foreach ($employees->groupBy('office_id') as $oid => $list) {
            $byOffice[$oid] = ['active' => $list->count(), 'present' => count(array_filter($todayRows, fn ($r) => (int) $r['office'] === (int) $oid))];
        }

        $rejections = AttendanceRejection::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('created_at', '>=', $today->subDays(6)->utc())
            ->selectRaw('reason_code, count(*) c')->groupBy('reason_code')->orderByDesc('c')->pluck('c', 'reason_code')->all();

        $workday = ! $today->isSunday();

        return [
            'active' => $employees->count(), 'present' => $present, 'late' => $late, 'on_time' => $present - $late, 'remote' => $remote,
            'absent' => $workday ? max(0, $employees->count() - $present - $remote) : 0, 'workday' => $workday,
            'on_time_rate' => $present ? (int) round(100 * ($present - $late) / $present) : null,
            'avg_present' => (int) round(array_sum($worked = array_filter(array_map(fn ($a, $b) => $a + $b, $trend['on_time'], $trend['late']))) / max(1, count($worked))),
            'trend' => $trend, 'arrivals' => $buckets, 'before7' => $early, 'by_office' => $byOffice, 'rejections' => $rejections,
            'shift' => sprintf('%02d:%02d', $h, $m), 'late_after' => (int) ($s?->late_after_min ?? 10),
        ];
    }

    /** Platform-wide series for Super Admin: one count query per day (14 queries, any DB). */
    public static function dailyCounts(string $model, string $column, int $days = 14, ?callable $where = null): array
    {
        $today = CarbonImmutable::now(self::TZ)->startOfDay();
        $out = ['labels' => [], 'values' => []];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = $today->subDays($i);
            $q = $model::withoutGlobalScopes()->where($column, '>=', $d->utc())->where($column, '<', $d->addDay()->utc());
            if ($where) { $where($q); }
            $out['labels'][] = $d->format('d M');
            $out['values'][] = $q->count();
        }

        return $out;
    }
}
