@extends('layouts.app', ['title' => 'Edit template · '.$template->name])
@section('content')
<form method="post" action="{{ route('admin.messaging.update', $template) }}" class="card space-y-4 max-w-3xl">
  @csrf @method('PUT')
  <div class="text-sm text-slate-500">{{ strtoupper($template->channel) }} · key <code>{{ $template->key }}</code></div>
  @if($template->channel === 'email')
    <x-field name="subject" label="Subject" :value="$template->subject" required />
    <p class="text-xs text-slate-500">Use placeholders like <code>@{{company_name}}</code>, <code>@{{admin_name}}</code>, <code>@{{employee_name}}</code>, <code>@{{amount}}</code>.</p>
  @else
    <x-field name="dlt_template_id" label="DLT template id" :value="$template->dlt_template_id" />
    <p class="text-xs text-slate-500">Body must match the DLT-approved text exactly; <code>{#var#}</code> slots are filled in order.</p>
  @endif
  <div><label class="label" for="b">Body</label><textarea id="b" name="body" rows="8" class="input font-mono" required>{{ old('body', $template->body) }}</textarea></div>
  <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $template->enabled))> Enabled</label>
  <div class="flex gap-2"><button class="btn">Save</button><a class="btn-ghost" href="{{ route('admin.messaging.index') }}">Cancel</a></div>
</form>
@endsection
