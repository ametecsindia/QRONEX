@extends('layouts.app', ['title' => $employee->exists ? 'Edit employee' : 'Add employee', 'crumb' => '<a class="hover:text-teal" href="'.route('portal.employees.index').'">Employees</a>'.($employee->exists ? ' · '.e($employee->employee_code) : ' · new')])
@section('content')
<form method="post" action="{{ $employee->exists ? route('portal.employees.update', $employee) : route('portal.employees.store') }}" class="grid lg:grid-cols-3 gap-4 items-start max-w-6xl">
  @csrf @if($employee->exists) @method('PUT') @endif
  <div class="lg:col-span-2 space-y-4">
    <div class="card grid sm:grid-cols-2 gap-4">
      <div class="card-t col-span-full">Person</div>
      <x-field name="name" label="Full name" :value="$employee->name" required />
      <x-field name="employee_code" label="Employee code" :value="$employee->employee_code" required hint="Used to sign in to the app" />
      <x-field name="email" label="Email" type="email" :value="$employee->email" hint="Activation code is sent here" />
      <x-field name="mobile" label="Mobile" :value="$employee->mobile" placeholder="+91 98xxxxxxxx" hint="…and here by SMS" />
    </div>
    <div class="card grid sm:grid-cols-2 gap-4">
      <div class="card-t col-span-full">Work</div>
      <x-field name="department" label="Department" :value="$employee->department" />
      <x-field name="designation" label="Designation" :value="$employee->designation" />
      <x-field name="hrms_ref" label="HRMS / payroll reference" :value="$employee->hrms_ref" hint="Appears in exports for payroll matching" />
    </div>
  </div>
  <div class="space-y-4">
    <div class="card space-y-4">
      <div class="card-t">Office &amp; status</div>
      <div><label class="label" for="f-office">Office</label><select id="f-office" name="office_id" class="input" required>@foreach($offices as $o)<option value="{{ $o->id }}" @selected(old('office_id', $employee->office_id) == $o->id)>{{ $o->name }}</option>@endforeach</select>
        <p class="text-xs text-slate-500 mt-1">Can punch at any gate of any office; this is for reports.</p></div>
      <div><label class="label" for="f-status">Status</label><select id="f-status" name="status" class="input">@foreach(['active', 'inactive'] as $s)<option @selected(old('status', $employee->status) === $s)>{{ $s }}</option>@endforeach</select>
        @if($employee->exists && $employee->status === 'active')<p class="text-xs text-amber-700 mt-1">Setting inactive signs out and revokes all of this employee's phones.</p>@endif</div>
    </div>
    <div class="flex gap-2"><button class="btn flex-1">Save employee</button><a class="btn-ghost" href="{{ $employee->exists ? route('portal.employees.show', $employee) : route('portal.employees.index') }}">Cancel</a></div>
  </div>
</form>
@endsection
