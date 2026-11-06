<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SupportTicket;
use App\Services\Notifier;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['open', 'answered', 'closed'], true) ? $request->query('status') : null;
        $area = array_key_exists((string) $request->query('area'), SupportTicket::AREAS) ? $request->query('area') : null;

        return view('admin.support.index', [
            'counts' => SupportTicket::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'urgent' => SupportTicket::where('status', '!=', 'closed')->whereIn('priority', ['urgent', 'high'])->count(),
            'enquiries' => SupportTicket::whereNull('tenant_id')->where('status', '!=', 'closed')->count(),
            'area' => $area,
            'tickets' => SupportTicket::with('tenant')->withCount('messages')->when($status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', '!=', 'closed'))
                ->when($area, fn ($q, $a) => $q->where('area', $a))
                ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('ref', 'like', "%$s%")->orWhere('subject', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('name', 'like', "%$s%")))
                ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 ELSE 2 END")->latest('updated_at')->paginate(50)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function show(SupportTicket $ticket)
    {
        return view('admin.support.show', ['ticket' => $ticket->load('messages.user', 'tenant')]);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $d = $request->validate(['body' => 'required|string|max:5000', 'close' => 'boolean']);
        $ticket->messages()->create(['user_id' => $request->user()->id, 'from_staff' => true, 'body' => $d['body']]);
        $ticket->update(['status' => $request->boolean('close') ? 'closed' : 'answered']);
        AuditLog::record('support.replied', $ticket->ref, [], $ticket->tenant_id);
        Notifier::email('ticket_reply', $ticket->email, ['name' => $ticket->name, 'ticket' => $ticket->ref, 'subject' => $ticket->subject, 'body' => $d['body']], $ticket->tenant_id);

        return back()->with('ok', 'Reply sent'.($request->boolean('close') ? ' and ticket closed.' : '.'));
    }

    public function close(SupportTicket $ticket)
    {
        $ticket->update(['status' => 'closed']);

        return back()->with('ok', 'Ticket closed.');
    }
}
