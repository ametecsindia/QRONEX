<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function edit()
    {
        return view('portal.company', ['tenant' => auth()->user()->tenant]);
    }

    public function update(Request $request)
    {
        $tenant = $request->user()->tenant;
        $d = $request->validate([
            'name' => 'required|string|max:150',
            'org_type' => 'nullable|string|max:60',
            'industry' => 'nullable|string|max:80',
            'state' => 'nullable|string|max:80',
            'city' => 'nullable|string|max:80',
            'address' => 'nullable|string|max:255',
            'gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'website' => 'nullable|string|max:150',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:1024',
        ]);

        if ($request->hasFile('logo')) {
            $d['logo_path'] = $request->file('logo')->store("logos/{$tenant->id}", 'public');
        }
        unset($d['logo']);

        $tenant->update($d);
        AuditLog::record('company.updated', $tenant->slug, array_keys($tenant->getChanges()));

        return back()->with('ok', 'Company profile saved.');
    }
}
