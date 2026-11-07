<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HelpController extends Controller
{
    private function mine(Request $r)
    {
        return SupportTicket::where('tenant_id', $r->user()->tenant_id);
    }

    public function index(Request $request)
    {
        return view('portal.help.index', ['tickets' => $this->mine($request)->withCount('messages')->latest('updated_at')->get()]);
    }

    public function store(Request $request)
    {
        $d = $request->validate(['subject' => 'required|string|max:150', 'area' => ['required', Rule::in(array_keys(SupportTicket::AREAS))],
            'priority' => 'required|in:normal,high,urgent', 'body' => 'required|string|max:5000']);
        $u = $request->user();
        $t = SupportTicket::create(collect($d)->except('body')->all() + ['tenant_id' => $u->tenant_id, 'user_id' => $u->id, 'name' => $u->name, 'email' => $u->email]);
        $t->messages()->create(['user_id' => $u->id, 'body' => $d['body']]);
        self::notifyStaff($t, $d['body']);

        return redirect()->route('portal.help.show', $t)->with('ok', "Ticket {$t->ref} created. We reply by email and here.");
    }

    public function show(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->tenant_id === $request->user()->tenant_id, 404);

        return view('portal.help.show', ['ticket' => $ticket->load('messages.user')]);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->tenant_id === $request->user()->tenant_id, 404);
        $d = $request->validate(['body' => 'required|string|max:5000']);
        $ticket->messages()->create(['user_id' => $request->user()->id, 'body' => $d['body']]);
        $ticket->update(['status' => 'open']);
        self::notifyStaff($ticket, $d['body']);

        return back()->with('ok', 'Reply sent.');
    }

    public static function notifyStaff(SupportTicket $t, string $body): void
    {
        // The inbox set in Super Admin → Email & SMS, not platform users' login emails (those may not be real mailboxes).
        Notifier::email('ticket_staff', \App\Support\MailSettings::supportAddress(), ['ticket' => $t->ref, 'subject' => $t->subject, 'from' => "{$t->name} <{$t->email}>", 'body' => $body]);
    }
}
