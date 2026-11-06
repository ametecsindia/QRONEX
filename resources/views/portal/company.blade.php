@extends('layouts.app', ['title' => 'Company profile', 'crumb' => 'Settings'])
@section('content')
<form method="post" action="{{ route('portal.company.update') }}" enctype="multipart/form-data" class="grid lg:grid-cols-3 gap-4 items-start max-w-6xl" x-data="{logo:null}">
  @csrf @method('PUT')
  <div class="lg:col-span-2 space-y-4">
    <div class="card grid sm:grid-cols-2 gap-4">
      <div class="card-t col-span-full">Organisation</div>
      <x-field class="sm:col-span-2" name="name" label="Registered name" :value="$tenant->name" required />
      <x-field name="org_type" label="Organisation type" :value="$tenant->org_type" placeholder="Private Limited" />
      <x-field name="industry" label="Industry" :value="$tenant->industry" />
      <x-field name="gstin" label="GSTIN" :value="$tenant->gstin" maxlength="15" hint="Printed on your invoices" />
      <x-field name="website" label="Website" :value="$tenant->website" />
    </div>
    <div class="card grid sm:grid-cols-2 gap-4">
      <div class="card-t col-span-full">Registered address</div>
      <x-field class="sm:col-span-2" name="address" label="Address" :value="$tenant->address" />
      <x-field name="city" label="City" :value="$tenant->city" />
      <x-field name="state" label="State" :value="$tenant->state" />
    </div>
  </div>
  <div class="space-y-4">
    <div class="card space-y-3">
      <div class="card-t">Logo</div>
      <label class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 hover:border-teal bg-slate-50 h-36 cursor-pointer overflow-hidden">
        <template x-if="logo"><img :src="logo" class="max-h-28 object-contain" alt="New logo"></template>
        <template x-if="!logo">@if($tenant->logo_path)<img src="{{ asset('storage/'.$tenant->logo_path) }}" class="max-h-28 object-contain" alt="Logo">@else<span class="text-sm text-slate-500">Choose PNG or JPG · max 1 MB</span>@endif</template>
        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="const f=$event.target.files[0]; logo = f ? URL.createObjectURL(f) : null">
      </label>
      <p class="text-xs text-slate-500">Shown on the entrance QR screen and the employee app.</p>
    </div>
    <div class="card text-sm space-y-1"><div class="text-xs text-slate-500">Workspace address</div><div class="font-semibold text-navy font-mono">qronex.app/{{ $tenant->slug }}</div><p class="text-xs text-slate-500">Employees type this company code in the app. Contact support to change it.</p></div>
    <button class="btn w-full">Save profile</button>
  </div>
</form>
@endsection
