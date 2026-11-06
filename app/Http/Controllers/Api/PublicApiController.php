<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ApiKeyAuth;
use App\Models\AttendanceEvent;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Tenant;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Public REST API v1. Every query is filtered by the key's tenant explicitly (no session = no global scope). */
class PublicApiController extends Controller
{
    private function tenant(Request $r): Tenant
    {
        return $r->attributes->get('tenant');
    }

    private function audit(Request $r, string $action, ?string $subject, array $meta = []): void
    {
        AuditLog::create(['tenant_id' => $this->tenant($r)->id, 'action' => "api.$action", 'subject' => $subject,
            'meta' => $meta + ['key' => $r->attributes->get('api_key')->prefix], 'ip' => $r->ip()]);
    }

    private function limit(Request $r): int
    {
        return min(max((int) $r->query('limit', 100), 1), 500);
    }

    private function page($paginator, callable $map): array
    {
        return ['data' => collect($paginator->items())->map($map)->values(), 'next_cursor' => $paginator->nextCursor()?->encode()];
    }

    private function officeIdOrFail(Request $r, ?string $code): ?int
    {
        if ($code === null) {
            return null;
        }
        $id = Office::withoutGlobalScopes()->where('tenant_id', $this->tenant($r)->id)->where('code', strtoupper($code))->value('id');
        abort_unless($id, 422, "Unknown office_code '$code'.");

        return $id;
    }

    private function employee(Request $r, string $code): Employee
    {
        return Employee::withoutGlobalScopes()->with('office')->where('tenant_id', $this->tenant($r)->id)->where('employee_code', $code)->firstOr(
            fn () => abort(ApiKeyAuth::error(404, 'not_found', "Employee '$code' not found."))
        );
    }

    /* ---- offices ---- */

    public function offices(Request $r)
    {
        $offices = Office::withoutGlobalScopes()->with('gates')->where('tenant_id', $this->tenant($r)->id)->orderBy('code')->get();

        return ['data' => $offices->map(fn ($o) => [
            'office_code' => $o->code, 'name' => $o->name, 'slug' => $o->slug, 'address' => $o->address, 'city' => $o->city, 'state' => $o->state,
            'lat' => $o->lat, 'lng' => $o->lng, 'geofence_radius_m' => $o->geofence_radius_m, 'timezone' => $o->timezone, 'is_active' => $o->is_active,
            'gates' => $o->gates->map(fn ($g) => ['gate_code' => $g->code, 'name' => $g->name, 'is_active' => $g->is_active])->values(),
        ])];
    }

    /* ---- employees ---- */

    public function employees(Request $r)
    {
        $p = Employee::withoutGlobalScopes()->with('office')->where('tenant_id', $this->tenant($r)->id)
            ->when($r->query('office_code'), fn ($q, $c) => $q->where('office_id', $this->officeIdOrFail($r, $c)))
            ->when(in_array($r->query('status'), ['active', 'inactive'], true), fn ($q) => $q->where('status', $r->query('status')))
            ->when($r->query('updated_since'), fn ($q, $d) => $q->where('updated_at', '>=', Carbon::parse($d)->utc()))
            ->orderBy('id')->cursorPaginate($this->limit($r));

        return $this->page($p, fn ($e) => $e->toApi());
    }

    public function showEmployee(Request $r, string $code)
    {
        return ['data' => $this->employee($r, $code)->toApi()];
    }

    public function createEmployee(Request $r)
    {
        $tid = $this->tenant($r)->id;
        $d = $r->validate($this->rules() + [
            'employee_code' => ['required', 'alpha_dash', 'max:40', Rule::unique('employees')->where('tenant_id', $tid)],
            'name' => 'required|string|max:120',
            'office_code' => 'required|string',
        ]);
        $e = Employee::create(collect($d)->except('office_code')->all() + ['tenant_id' => $tid, 'office_id' => $this->officeIdOrFail($r, $d['office_code']), 'status' => 'active']);
        $this->audit($r, 'employee.created', $e->employee_code);

        return response()->json(['data' => $e->load('office')->toApi()], 201);
    }

    public function updateEmployee(Request $r, string $code)
    {
        $e = $this->employee($r, $code);
        $d = $r->validate($this->rules() + ['name' => 'sometimes|required|string|max:120', 'office_code' => 'sometimes|required|string']);
        if (array_key_exists('office_code', $d)) {
            $d['office_id'] = $this->officeIdOrFail($r, $d['office_code']);
            unset($d['office_code']);
        }
        $e->update($d);
        $this->audit($r, 'employee.updated', $e->employee_code, array_keys($e->getChanges()));

        return ['data' => $e->load('office')->toApi()];
    }

    /** Deactivates the employee and revokes every device (the app is signed out). */
    public function deactivateEmployee(Request $r, string $code)
    {
        $e = $this->employee($r, $code);
        if ($e->status !== 'inactive') {
            $revoked = $e->deactivate();
            $this->audit($r, 'employee.deactivated', $e->employee_code, ['devices_revoked' => $revoked]);
        }

        return ['data' => $e->toApi()];
    }

    private function rules(): array
    {
        return [
            'email' => 'nullable|email|max:190', 'mobile' => 'nullable|string|max:20', 'department' => 'nullable|string|max:80',
            'designation' => 'nullable|string|max:80', 'hrms_ref' => 'nullable|string|max:60',
        ];
    }

    /* ---- attendance ---- */

    private function eventsQuery(Request $r)
    {
        $tz = 'Asia/Kolkata';
        $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'type' => 'nullable|in:IN,OUT']);
        $from = Carbon::parse($r->query('from', today($tz)->toDateString()), $tz)->startOfDay()->utc();
        $to = Carbon::parse($r->query('to', $r->query('from', today($tz)->toDateString())), $tz)->endOfDay()->utc();
        abort_if($from->diffInDays($to) > 93, 422, 'Date range is limited to 93 days.');
        $tid = $this->tenant($r)->id;

        return AttendanceEvent::withoutGlobalScopes()->with('employee', 'office', 'gate')->where('tenant_id', $tid)
            ->whereBetween('recorded_at', [$from, $to])
            ->when($r->query('office_code'), fn ($q, $c) => $q->where('office_id', $this->officeIdOrFail($r, $c)))
            ->when($r->query('employee_code'), fn ($q, $c) => $q->whereHas('employee', fn ($e) => $e->withoutGlobalScopes()->where('tenant_id', $tid)->where('employee_code', $c)))
            ->when($r->query('type'), fn ($q, $t) => $q->where('type', $t));
    }

    public function events(Request $r)
    {
        return $this->page($this->eventsQuery($r)->orderBy('recorded_at')->orderBy('id')->cursorPaginate($this->limit($r)), fn ($e) => $e->toApi());
    }

    public function showEvent(Request $r, string $ref)
    {
        $e = AttendanceEvent::withoutGlobalScopes()->with('employee', 'office', 'gate')->where('tenant_id', $this->tenant($r)->id)->where('ref', $ref)->first();

        return $e ? ['data' => $e->toApi()] : ApiKeyAuth::error(404, 'not_found', "Event '$ref' not found.");
    }

    public function export(Request $r)
    {
        $q = $this->eventsQuery($r)->orderBy('recorded_at')->orderBy('id');
        $this->audit($r, 'attendance.exported', null, $r->only('from', 'to', 'office_code'));

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'employee_code', 'employee_name', 'hrms_ref', 'type', 'date_ist', 'time_ist', 'office_code', 'gate_code', 'mode', 'distance_m', 'accuracy_m']);
            $q->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $e) {
                    $t = $e->recorded_at->timezone('Asia/Kolkata');
                    fputcsv($out, [$e->ref, $e->employee?->employee_code, $e->employee?->name, $e->employee?->hrms_ref, $e->type, $t->toDateString(), $t->format('H:i:s'), $e->office?->code, $e->gate?->code, $e->mode, $e->distance_m, $e->accuracy_m]);
                }
            });
            fclose($out);
        }, 'qronex-attendance.csv', ['Content-Type' => 'text/csv']);
    }

    /* ---- remote permissions (granted already-approved; the key is the approver) ---- */

    public function remotePermissions(Request $r)
    {
        $p = \App\Models\RemotePermission::withoutGlobalScopes()->with('employee')->where('tenant_id', $this->tenant($r)->id)
            ->when($r->query('status'), fn ($q, $s) => $q->where('status', $s))->orderByDesc('id')->cursorPaginate($this->limit($r));

        return $this->page($p, fn ($x) => $x->toApi());
    }

    public function grantRemote(Request $r)
    {
        $d = $r->validate([
            'employee_code' => 'required|string', 'type' => 'required|in:'.implode(',', array_keys(\App\Models\RemotePermission::TYPES)),
            'from' => 'required|date', 'to' => 'required|date|after_or_equal:from', 'reason' => 'required|string|max:255',
            'location_waived' => 'boolean', 'location_label' => 'nullable|string|max:120',
            'lat' => 'required_unless:location_waived,true|nullable|numeric|between:-90,90', 'lng' => 'required_with:lat|nullable|numeric|between:-180,180',
            'radius_m' => 'nullable|integer|between:50,5000',
        ]);
        $e = $this->employee($r, $d['employee_code']);
        $waived = (bool) ($d['location_waived'] ?? false);
        $p = \App\Models\RemotePermission::create([
            'ref' => \App\Services\AttendanceVerifier::ref('RP'), 'tenant_id' => $e->tenant_id, 'employee_id' => $e->id, 'type' => $d['type'],
            'from_date' => $d['from'], 'to_date' => $d['to'], 'reason' => $d['reason'], 'location_waived' => $waived,
            'location_label' => $waived ? null : ($d['location_label'] ?? null), 'lat' => $waived ? null : $d['lat'], 'lng' => $waived ? null : $d['lng'],
            'radius_m' => $waived ? null : ($d['radius_m'] ?? 250), 'status' => 'approved', 'requested_via' => 'api', 'decided_at' => now(),
        ])->setRelation('employee', $e);
        $this->audit($r, 'remote.granted', $p->ref, ['employee' => $e->employee_code]);
        \App\Services\Approvals::remoteApproved($p);

        return response()->json(['data' => $p->toApi()], 201);
    }

    /* ---- webhooks ---- */

    private function hookJson(WebhookEndpoint $w, bool $withSecret = false): array
    {
        return ['id' => $w->id, 'url' => $w->url, 'description' => $w->description, 'events' => $w->events, 'is_active' => $w->is_active] + ($withSecret ? ['secret' => $w->secret] : []);
    }

    public function webhooks(Request $r)
    {
        return ['data' => WebhookEndpoint::withoutGlobalScopes()->where('tenant_id', $this->tenant($r)->id)->get()->map(fn ($w) => $this->hookJson($w))];
    }

    public function createWebhook(Request $r)
    {
        $d = $r->validate(['url' => 'required|url|max:500', 'description' => 'nullable|string|max:120',
            'events' => 'required|array|min:1', 'events.*' => Rule::in(array_keys(WebhookEndpoint::EVENTS))]);
        if ($problem = Webhooks::urlProblem($d['url'])) {
            return ApiKeyAuth::error(422, 'invalid_url', $problem);
        }
        $w = WebhookEndpoint::create($d + ['tenant_id' => $this->tenant($r)->id, 'secret' => 'whsec_'.Str::random(32), 'events' => array_values(array_unique($d['events']))]);
        $this->audit($r, 'webhook.created', $w->url);

        return response()->json(['data' => $this->hookJson($w, true)], 201);
    }

    public function deleteWebhook(Request $r, int $id)
    {
        $w = WebhookEndpoint::withoutGlobalScopes()->where('tenant_id', $this->tenant($r)->id)->find($id);
        if (! $w) {
            return ApiKeyAuth::error(404, 'not_found', 'Webhook not found.');
        }
        $w->delete();
        $this->audit($r, 'webhook.deleted', $w->url);

        return response()->noContent();
    }
}
