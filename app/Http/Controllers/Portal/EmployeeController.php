<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    private const CSV_COLUMNS = ['employee_code', 'name', 'email', 'mobile', 'department', 'designation', 'office_code', 'hrms_ref'];

    private function offices()
    {
        $scope = auth()->user()->officeScope();

        return Office::with(['gates' => fn ($q) => $q->where('is_active', true)->orderBy('name')])->when($scope !== null, fn ($q) => $q->whereIn('id', $scope))->orderBy('code')->get();
    }

    private function authorizeEmployee(Employee $employee): void
    {
        $scope = auth()->user()->officeScope();
        abort_if($scope !== null && ! in_array((int) $employee->office_id, array_map('intval', $scope), true), 403);
    }

    public function index(Request $request)
    {
        $scope = $request->user()->officeScope();
        $employees = Employee::with('office')
            ->withCount(['devices as approved_devices_count' => fn ($q) => $q->where('status', 'approved')])
            ->when($scope !== null, fn ($q) => $q->whereIn('office_id', $scope))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('employee_code', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))
            ->when($request->office, fn ($q, $o) => $q->where('office_id', $o))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy(in_array($request->sort, ['employee_code', 'name', 'department', 'status', 'created_at'], true) ? $request->sort : 'employee_code', $request->dir === 'desc' ? 'desc' : 'asc')
            ->paginate(50)->withQueryString();

        $all = Employee::when($scope !== null, fn ($q) => $q->whereIn('office_id', $scope));
        $counts = [
            'active' => (clone $all)->where('status', 'active')->count(),
            'inactive' => (clone $all)->where('status', 'inactive')->count(),
            'no_phone' => (clone $all)->where('status', 'active')->whereDoesntHave('devices', fn ($q) => $q->where('status', 'approved'))->count(),
            'pending' => \App\Models\Device::where('status', 'pending')->whereIn('employee_id', (clone $all)->select('id'))->count(),
        ];

        return view('portal.employees.index', ['employees' => $employees, 'offices' => $this->offices(), 'counts' => $counts]);
    }

    public function show(Employee $employee)
    {
        $this->authorizeEmployee($employee);
        $tz = 'Asia/Kolkata';
        $s = \App\Models\TenantSetting::where('tenant_id', $employee->tenant_id)->first();
        [$h, $m] = array_map('intval', explode(':', $s?->shift_start ?: '09:30'));
        $lateAfter = $h * 60 + $m + (int) ($s?->late_after_min ?? 10);

        $events = $employee->events()->with('gate', 'office', 'remotePermission')->where('recorded_at', '>=', now()->subDays(30))->orderBy('recorded_at')->get();
        $days = $events->groupBy(fn ($e) => $e->recorded_at->timezone($tz)->toDateString())->map(function ($g) use ($tz, $lateAfter) {
            $in = $g->firstWhere('type', 'IN');
            $out = $g->where('type', 'OUT')->last();
            $t = $in?->recorded_at->timezone($tz);
            $late = $t && ($t->hour * 60 + $t->minute) > $lateAfter;

            return ['in' => $t, 'out' => $out?->recorded_at->timezone($tz), 'late' => $late, 'count' => $g->count(),
                'hours' => $t && $out && $out->recorded_at->gt($in->recorded_at) ? round($in->recorded_at->diffInMinutes($out->recorded_at) / 60, 1) : null,
                'where' => $in?->remotePermission ? 'Remote' : ($in?->office?->name ?? '—')];
        })->sortKeysDesc();
        $arr = $days->pluck('in')->filter()->map(fn ($t) => $t->hour * 60 + $t->minute);

        return view('portal.employees.show', [
            'employee' => $employee->load('office', 'devices'),
            'days' => $days,
            'kpi' => [
                'present' => $days->count(),
                'late' => $days->where('late', true)->count(),
                'avg_in' => $arr->count() ? sprintf('%02d:%02d', intdiv((int) $arr->avg(), 60), (int) $arr->avg() % 60) : null,
                'avg_hours' => $days->pluck('hours')->filter()->avg(),
            ],
            'rejections' => \App\Models\AttendanceRejection::where('employee_id', $employee->id)->latest('created_at')->take(10)->get(),
            'remote' => \App\Models\RemotePermission::where('employee_id', $employee->id)->latest()->take(10)->get(),
            'shift' => $s?->shift_start ?: '09:30',
        ]);
    }

    public function create()
    {
        return view('portal.employees.form', ['employee' => new Employee(['status' => 'active']), 'offices' => $this->offices()]);
    }

    public function store(Request $request)
    {
        $e = Employee::create($this->validated($request));
        AuditLog::record('employee.created', $e->employee_code);

        return redirect()->route('portal.employees.index')->with('ok', "Employee {$e->name} added.");
    }

    public function edit(Employee $employee)
    {
        $this->authorizeEmployee($employee);

        return view('portal.employees.form', ['employee' => $employee, 'offices' => $this->offices()]);
    }

    public function update(Request $request, Employee $employee)
    {
        $this->authorizeEmployee($employee);
        $d = $this->validated($request, $employee);
        $deactivating = $employee->status === 'active' && $d['status'] === 'inactive';
        $employee->update(collect($d)->except('status')->all() + ($deactivating ? [] : ['status' => $d['status']]));
        $revoked = $deactivating ? $employee->deactivate() : 0;
        AuditLog::record('employee.updated', $employee->employee_code, array_keys($employee->getChanges()) + ($deactivating ? ['devices_revoked' => $revoked] : []));

        return redirect()->route('portal.employees.index')->with('ok', 'Employee saved.');
    }

    /** Single-use code (72 h) the employee types into the app. Shown once on screen and sent by SMS + email. */
    public function activation(Employee $employee)
    {
        $this->authorizeEmployee($employee);
        abort_if($employee->status !== 'active', 422, 'Employee is inactive.');

        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $employee->forceFill([
            'activation_code_hash' => \Illuminate\Support\Facades\Hash::make($code),
            'activation_expires_at' => now()->addHours(config('qronex.activation_hours')),
        ])->save();

        $tenant = auth()->user()->tenant;
        $pretty = substr($code, 0, 4).'-'.substr($code, 4);
        \App\Services\Notifier::sms('activate', $employee->mobile, [$tenant->name, $pretty], $tenant->id);
        \App\Services\Notifier::email('activation', $employee->email, ['employee_name' => $employee->name, 'company_name' => $tenant->name, 'company_code' => $tenant->slug, 'employee_code' => $employee->employee_code, 'activation_code' => $pretty, 'hours' => config('qronex.activation_hours')], $tenant->id);
        AuditLog::record('employee.activation_sent', $employee->employee_code);

        return back()->with('ok', "Activation for {$employee->name} — company code: {$tenant->slug} · employee code: {$employee->employee_code} · activation code: {$pretty} (valid ".config('qronex.activation_hours')." h, single use).");
    }

    public function importForm()
    {
        return view('portal.employees.import', ['offices' => $this->offices()]);
    }

    public function template()
    {
        return response()->streamDownload(function () {
            echo implode(',', self::CSV_COLUMNS)."\n";
            echo "EMP0101,Ravi Kumar,ravi.kumar@example.com,+919800000001,Collections,Collection Executive,HYD01,HR-9001\n";
        }, 'qronex-employees-template.csv', ['Content-Type' => 'text/csv']);
    }

    /** All-or-nothing: any invalid row → nothing is imported and every row error is shown. */
    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);
        $tid = $request->user()->tenant_id;
        $officeIds = $this->offices()->pluck('id', 'code'); // code => id, already office-scoped

        $fh = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $h))), fgetcsv($fh) ?: []);
        if (array_diff(['employee_code', 'name', 'office_code'], $header)) {
            return back()->withErrors(['file' => 'Header must include employee_code, name and office_code. Download the template.']);
        }

        $rows = $errors = $seen = [];
        $line = 1;
        while (($cells = fgetcsv($fh)) !== false) {
            $line++;
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $row = array_map('trim', array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), '')));
            $v = Validator::make($row, [
                'employee_code' => ['required', 'alpha_dash', 'max:40', Rule::unique('employees')->where('tenant_id', $tid)],
                'name' => 'required|string|max:120',
                'email' => 'nullable|email|max:190',
                'mobile' => 'nullable|string|max:20',
                'office_code' => ['required', Rule::in($officeIds->keys()->all())],
            ]);
            $msgs = $v->errors()->all();
            if (isset($seen[$row['employee_code']])) {
                $msgs[] = "Duplicate employee_code in file (also on line {$seen[$row['employee_code']]}).";
            }
            $seen[$row['employee_code']] = $line;
            if ($msgs) {
                $errors[$line] = $msgs;
                continue;
            }
            $rows[] = [
                'tenant_id' => $tid, 'office_id' => $officeIds[$row['office_code']], 'employee_code' => $row['employee_code'],
                'name' => $row['name'], 'email' => $row['email'] ?? null ?: null, 'mobile' => $row['mobile'] ?? null ?: null,
                'department' => $row['department'] ?? null ?: null, 'designation' => $row['designation'] ?? null ?: null,
                'hrms_ref' => $row['hrms_ref'] ?? null ?: null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ];
        }
        fclose($fh);

        if ($errors || ! $rows) {
            return back()->with('importErrors', $errors)->withErrors(['file' => $errors ? count($errors).' row(s) have errors. Nothing was imported.' : 'The file has no data rows.']);
        }

        DB::transaction(fn () => collect($rows)->chunk(500)->each(fn ($c) => Employee::insert($c->all())));
        AuditLog::record('employee.imported', null, ['rows' => count($rows)]);

        return redirect()->route('portal.employees.index')->with('ok', count($rows).' employees imported.');
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        $tid = $request->user()->tenant_id;

        return $request->validate([
            'employee_code' => ['required', 'alpha_dash', 'max:40', Rule::unique('employees')->where('tenant_id', $tid)->ignore($employee)],
            'name' => 'required|string|max:120',
            'email' => 'nullable|email|max:190',
            'mobile' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:80',
            'designation' => 'nullable|string|max:80',
            'hrms_ref' => 'nullable|string|max:60',
            'office_id' => ['required', Rule::in($this->offices()->pluck('id')->all())],
            'gate_id' => ['nullable', Rule::exists('gates', 'id')->where('office_id', $request->input('office_id'))],
            'scan_scope' => 'required|in:'.implode(',', array_keys(Employee::SCAN_SCOPES)),
            'status' => 'required|in:active,inactive',
        ], ['gate_id.exists' => 'Pick a gate of the selected office.']) + (
            // Extra phones are an exception only a company admin can grant.
            $request->user()->can('manage-company') && $request->has('max_devices')
                ? ['max_devices' => (int) $request->validate(['max_devices' => 'required|integer|between:1,'.\App\Models\PlatformSetting::get('device_limit_max', 3)])['max_devices'] ?: null]
                : []
        );
    }
}
