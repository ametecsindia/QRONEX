<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Services\Notifier;
use Illuminate\Http\Request;

class MessagingController extends Controller
{
    public function index()
    {
        return view('admin.messaging.index', [
            'templates' => MessageTemplate::orderBy('channel')->orderBy('name')->get(),
            'logs' => MessageLog::with('tenant')->when(request('channel'), fn ($q, $c) => $q->where('channel', $c))->when(request('status'), fn ($q, $s) => $q->where('status', $s))
                ->latest('created_at')->take(50)->get(),
            'week' => MessageLog::where('created_at', '>=', now()->subDays(7))->selectRaw('channel, status, count(*) c')->groupBy('channel', 'status')->get()
                ->groupBy('channel')->map(fn ($g) => $g->pluck('c', 'status')),
            'mail' => ['mailer' => config('mail.default'), 'host' => config('mail.mailers.smtp.host'), 'from' => config('mail.from.address')],
            'sms' => ['driver' => config('qronex.sms.driver'), 'sender' => config('qronex.sms.sender'), 'url' => config('qronex.sms.url')],
        ]);
    }

    public function edit(MessageTemplate $template)
    {
        return view('admin.messaging.edit', compact('template'));
    }

    public function update(Request $request, MessageTemplate $template)
    {
        $d = $request->validate([
            'subject' => [$template->channel === 'email' ? 'required' : 'nullable', 'string', 'max:190'],
            'body' => ['required', 'string', $template->channel === 'sms' ? 'max:500' : 'max:5000'],
            'dlt_template_id' => 'nullable|string|max:40',
        ]);
        $template->update($d + ['enabled' => $request->boolean('enabled')]);
        AuditLog::record('template.updated', "{$template->channel}:{$template->key}");

        return redirect()->route('admin.messaging.index')->with('ok', "Template “{$template->name}” saved.");
    }

    public function test(Request $request)
    {
        $d = $request->validate(['channel' => 'required|in:email,sms', 'to' => 'required|string|max:190']);
        $d['channel'] === 'email'
            ? Notifier::email('security', $d['to'], ['event' => 'a test message from QRONEX admin', 'time' => now('Asia/Kolkata')->format('d M Y H:i')])
            : Notifier::sms('otp', $d['to'], ['123456', '5']);
        $log = MessageLog::latest('id')->first();

        return back()->with('ok', "Test {$d['channel']}: {$log?->status}".($log?->error ? " — {$log->error}" : ''));
    }
}
