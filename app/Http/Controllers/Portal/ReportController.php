<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Raw attendance + clearly marked adjustments. Salary, leave and overtime are out of QRONEX's scope. */
class ReportController extends Controller
{
    public const REPORTS = [
        'register' => ['Daily punch register', 'Every verified punch for one day, all offices'],
        'monthly' => ['Monthly attendance register', 'Per employee per day: first IN, last OUT, hours on site'],
        'remote' => ['Remote attendance log', 'WFH / field punches with their permission reference'],
        'adjustments' => ['Adjustments log', 'Manual punches added from approved corrections'],
    ];

    public function index(Request $request)
    {
        $scope = $request->user()->officeScope();

        return view('portal.reports', ['reports' => self::REPORTS, 'offices' => Office::when($scope !== null, fn ($q) => $q->whereIn('id', $scope))->orderBy('code')->get()]);
    }

    public function download(Request $request, string $report)
    {
        abort_unless(isset(self::REPORTS[$report]), 404);
        $d = $request->validate(['date' => 'nullable|date', 'month' => 'nullable|date_format:Y-m', 'office' => 'nullable|integer']);
        $tz = 'Asia/Kolkata';
        $scope = $request->user()->officeScope();
        $base = fn () => AttendanceEvent::with('employee', 'office', 'gate', 'remotePermission', 'correction')
            ->when($scope !== null, fn ($q) => $q->whereHas('employee', fn ($e) => $e->whereIn('office_id', $scope)))
            ->when($d['office'] ?? null, fn ($q, $o) => $q->whereHas('employee', fn ($e) => $e->where('office_id', $o)));

        if ($report === 'monthly') {
            $month = Carbon::createFromFormat('Y-m', $d['month'] ?? now($tz)->format('Y-m'), $tz)->startOfMonth();

            return self::monthly($base(), $month, $scope, $d['office'] ?? null);
        }

        $day = Carbon::parse($d['date'] ?? now($tz)->toDateString(), $tz);
        [$from, $to] = $report === 'register' ? [$day->copy()->startOfDay(), $day->copy()->endOfDay()] : [$day->copy()->subDays(30)->startOfDay(), $day->copy()->endOfDay()];
        $q = $base()->whereBetween('recorded_at', [$from->utc(), $to->utc()])->orderBy('recorded_at')
            ->when($report === 'remote', fn ($q) => $q->whereNotNull('remote_permission_id'))
            ->when($report === 'adjustments', fn ($q) => $q->where('adjusted', true));

        return self::csv("qronex-$report-{$day->format('Ymd')}.csv", ['ref', 'employee_code', 'employee_name', 'hrms_ref', 'type', 'date_ist', 'time_ist', 'mode', 'office', 'gate', 'reference', 'note'],
            fn ($out) => $q->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $e) {
                    $t = $e->recorded_at->timezone('Asia/Kolkata');
                    fputcsv($out, [$e->ref, $e->employee->employee_code, $e->employee->name, $e->employee->hrms_ref, $e->type, $t->toDateString(), $t->format('H:i:s'), $e->mode,
                        $e->office?->name, $e->gate?->name, $e->remotePermission?->ref ?? $e->correction?->ref, $e->adjusted ? 'ADJUSTED: '.$e->correction?->reason : '']);
                }
            }));
    }

    private static function monthly($query, Carbon $month, ?array $scope, $office)
    {
        $end = $month->copy()->endOfMonth();
        $events = $query->whereBetween('recorded_at', [$month->copy()->utc(), $end->copy()->utc()])->orderBy('recorded_at')->get()
            ->groupBy(fn ($e) => $e->employee_id.'|'.$e->recorded_at->timezone('Asia/Kolkata')->toDateString());
        $employees = Employee::with('office')->when($scope !== null, fn ($q) => $q->whereIn('office_id', $scope))->when($office, fn ($q, $o) => $q->where('office_id', $o))->orderBy('employee_code')->get();

        return self::csv("qronex-monthly-{$month->format('Y-m')}.csv", ['employee_code', 'employee_name', 'hrms_ref', 'office', 'date', 'weekday', 'status', 'first_in', 'last_out', 'hours_on_site', 'punches', 'adjusted'],
            function ($out) use ($employees, $events, $month, $end) {
                foreach ($employees as $emp) {
                    for ($d = $month->copy(); $d->lte($end) && $d->lte(now('Asia/Kolkata')); $d->addDay()) {
                        $day = $events->get($emp->id.'|'.$d->toDateString(), collect());
                        $in = $day->firstWhere('type', 'IN');
                        $out_ = $day->where('type', 'OUT')->last();
                        $hours = $in && $out_ && $out_->recorded_at->gt($in->recorded_at) ? round($in->recorded_at->diffInMinutes($out_->recorded_at) / 60, 2) : '';
                        fputcsv($out, [$emp->employee_code, $emp->name, $emp->hrms_ref, $emp->office?->name, $d->toDateString(), $d->format('D'),
                            $day->isEmpty() ? 'NO PUNCH' : ($day->contains('remote_permission_id', '!=', null) ? 'REMOTE' : 'PRESENT'),
                            $in?->recorded_at->timezone('Asia/Kolkata')->format('H:i'), $out_?->recorded_at->timezone('Asia/Kolkata')->format('H:i'), $hours, $day->count(), $day->where('adjusted', true)->count()]);
                    }
                }
            });
    }

    /** CSV by default; ?format=xlsx turns the same rows into an Excel file. */
    public static function csv(string $name, array $header, callable $rows)
    {
        if (request('format') === 'xlsx') {
            $tmp = fopen('php://temp', 'r+');
            $rows($tmp);
            rewind($tmp);
            $all = [$header];
            while (($r = fgetcsv($tmp)) !== false) {
                $all[] = $r;
            }
            fclose($tmp);

            return response(\App\Services\Xlsx::build($all, 'QRONEX'), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.preg_replace('/\.csv$/', '.xlsx', $name).'"',
            ]);
        }

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows names correctly
            fputcsv($out, $header);
            $rows($out);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
