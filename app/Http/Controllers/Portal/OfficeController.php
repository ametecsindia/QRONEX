<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Gate;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OfficeController extends Controller
{
    public function index()
    {
        return view('portal.offices.index', ['offices' => Office::with('gates')->withCount('employees')->orderBy('code')->get()]);
    }

    public function create()
    {
        return view('portal.offices.form', ['office' => new Office(['lat' => 17.385, 'lng' => 78.4867, 'geofence_radius_m' => 150, 'timezone' => 'Asia/Kolkata', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $office = Office::create($this->validated($request));
        $office->gates()->create(['tenant_id' => $office->tenant_id, 'code' => 'gate1', 'name' => 'Main Entrance']);
        AuditLog::record('office.created', $office->code);

        return redirect()->route('portal.offices.index')->with('ok', "Office {$office->name} created with a default gate.");
    }

    public function edit(Office $office)
    {
        return view('portal.offices.form', compact('office'));
    }

    public function update(Request $request, Office $office)
    {
        $office->update($this->validated($request, $office));
        AuditLog::record('office.updated', $office->code, array_keys($office->getChanges()));

        return redirect()->route('portal.offices.index')->with('ok', 'Office saved.');
    }

    public function storeGate(Request $request, Office $office)
    {
        $d = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('gates')->where('office_id', $office->id)],
            'name' => 'required|string|max:80',
        ]);
        $office->gates()->create($d + ['tenant_id' => $office->tenant_id]);
        AuditLog::record('gate.created', "{$office->code}/{$d['code']}");

        return back()->with('ok', 'Gate added.');
    }

    /** New secret → every screen showing the old link stops getting codes. */
    public function resetDisplay(Gate $gate)
    {
        $gate->forceFill(['display_secret' => Str::random(40)])->save();
        AuditLog::record('gate.display_reset', $gate->code);

        return back()->with('ok', "Display link for {$gate->name} reset. Open the new link on the screen.");
    }

    public function toggleGate(Gate $gate)
    {
        $gate->update(['is_active' => ! $gate->is_active]);
        AuditLog::record($gate->is_active ? 'gate.enabled' : 'gate.disabled', $gate->code);

        return back()->with('ok', 'Gate updated.');
    }

    private function validated(Request $request, ?Office $office = null): array
    {
        $tid = $request->user()->tenant_id;
        $request->merge(['slug' => Str::slug($request->input('slug') ?: $request->input('name'))]);

        $d = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:20', Rule::unique('offices')->where('tenant_id', $tid)->ignore($office)],
            'slug' => ['required', 'max:60', Rule::unique('offices')->where('tenant_id', $tid)->ignore($office)],
            'name' => 'required|string|max:120',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:80',
            'state' => 'nullable|string|max:80',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'geofence_radius_m' => 'required|integer|between:30,2000',
            'timezone' => ['required', Rule::in(timezone_identifiers_list())],
        ]);
        $d['code'] = strtoupper($d['code']);
        $d['is_active'] = $request->boolean('is_active');

        return $d;
    }
}
