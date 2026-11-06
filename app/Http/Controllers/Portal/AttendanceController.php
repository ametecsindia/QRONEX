<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRejection;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    private function filtered(Request $request, string $model)
    {
        $tz = 'Asia/Kolkata';
        $from = Carbon::parse($request->query('from', today($tz)->toDateString()), $tz)->startOfDay()->utc();
        $to = Carbon::parse($request->query('to', today($tz)->toDateString()), $tz)->endOfDay()->utc();
        $scope = $request->user()->officeScope();
        $col = $model === AttendanceEvent::class ? 'recorded_at' : 'created_at';

        return $model::with($model === AttendanceEvent::class ? ['employee.office', 'gate.office', 'correction'] : ['employee.office', 'gate.office'])
            ->whereBetween($col, [$from, $to])
            ->when($scope !== null, fn ($q) => $q->whereHas('employee', fn ($e) => $e->whereIn('office_id', $scope)))
            ->when($request->office, fn ($q, $o) => $q->whereHas('employee', fn ($e) => $e->where('office_id', $o)))
            ->when($request->q, fn ($q, $s) => $q->whereHas('employee', fn ($e) => $e->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('employee_code', 'like', "%$s%"))))
            ->latest($col);
    }

    public function index(Request $request)
    {
        $tab = $request->query('tab') === 'rejected' ? 'rejected' : 'verified';
        $scope = $request->user()->officeScope();

        return view('portal.attendance.index', [
            'tab' => $tab,
            'rows' => $this->filtered($request, $tab === 'rejected' ? AttendanceRejection::class : AttendanceEvent::class)->paginate(100)->withQueryString(),
            'offices' => Office::when($scope !== null, fn ($q) => $q->whereIn('id', $scope))->orderBy('code')->get(),
        ]);
    }

    public function export(Request $request)
    {
        $rows = $this->filtered($request, AttendanceEvent::class)->reorder('recorded_at');

        return ReportController::csv('qronex-attendance-'.now('Asia/Kolkata')->format('Ymd-His').'.csv',
            ['ref', 'employee_code', 'employee_name', 'hrms_ref', 'type', 'date_ist', 'time_ist', 'office', 'gate', 'distance_m', 'accuracy_m', 'mode', 'adjusted'],
            fn ($out) => $rows->chunk(1000, function ($chunk) use ($out) {
                foreach ($chunk as $e) {
                    $t = $e->recorded_at->timezone('Asia/Kolkata');
                    fputcsv($out, [$e->ref, $e->employee->employee_code, $e->employee->name, $e->employee->hrms_ref, $e->type, $t->toDateString(), $t->format('H:i:s'), $e->gate?->office?->name, $e->gate?->name, $e->distance_m, $e->accuracy_m, $e->mode, $e->adjusted ? 'yes' : '']);
                }
            }));
    }
}
