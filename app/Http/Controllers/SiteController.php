<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Portal\HelpController;
use App\Models\Plan;
use App\Models\SupportTicket;
use App\Services\PriceCalculator;
use Illuminate\Http\Request;

/** Public website (copy from the Stage 1 prototype). Prices come live from Super Admin → Plans. */
class SiteController extends Controller
{
    public const TOPICS = ['demo' => 'A demo', 'pricing' => 'Pricing', 'integration' => 'Integration', 'other' => 'Something else'];

    public const HOW = [['Register your company', 'Sign up online, verify your email and claim your QRONEX address.'], ['Create office locations', 'Add each branch with its address and entrance gates.'], ['Configure geofencing', 'Drop a pin on the map and set the radius employees must be inside.'], ['Add employees', 'Add people one by one or import a CSV from your HRMS.'], ['Approve employee devices', 'Each employee registers one phone. You approve it once.'], ['Open the QR display URL', 'Open the gate link on any tablet, TV or laptop browser at the entrance.'], ['Start attendance', 'Employees scan with the QRONEX app. Each punch is verified on the server.'], ['Download logs or connect APIs', 'Export CSV, or stream events to your HRMS by API and webhook.']];

    public const FEATURES = [['Dynamic QR code', 'A new signed code on every rotation; screenshots stop working within seconds.'], ['Configurable 3-second rotation', 'Default 3 s, adjustable within platform security bounds.'], ['GPS geofencing', 'Per-office radius with accuracy thresholds.'], ['Registered mobile devices', 'One approved phone per employee by default.'], ['Employee authentication', 'App sign-in bound to a device credential.'], ['Multiple offices', 'Each office has its own geofence and timezone.'], ['Multiple entrance displays', 'Separate QR per gate, each with its own link.'], ['Remote / WFH approval', 'Date-bound permissions with optional home geofence.'], ['Real-time attendance ledger', 'Every accepted punch gets a unique event ID.'], ['CSV export & reports', 'Daily register, monthly register, remote and adjustment logs.'], ['REST API integration', 'Scoped, tenant-specific keys with rate limits.'], ['Webhook integration', 'Signed event delivery with automatic retries.'], ['Cloud-based administration', 'Nothing to install at the office except a browser.'], ['Subscription management', 'Plans, invoices and GST in one place.'], ['Attendance audit logs', 'Corrections are separate entries; history is never rewritten.']];

    public const INDUSTRIES = [['BPOs and Call Centres', 'Three shifts, one entrance tablet per floor. Night-shift punches land in the ledger in real time.', 'Shift-heavy · multi-floor'], ['Debt Collection Agencies', 'Field collectors use approved field-duty permissions; office tele-callers scan at the branch gate.', 'Office + field mix'], ['Corporate Offices', 'Replace the lobby biometric with a TV-mounted QR and push events into your HRMS by webhook.', 'HRMS integration'], ['Retail Chains', 'Every store gets a tablet and its own geofence. Regional managers see only their stores.', 'Many small sites'], ['Factories', 'Gate-wise displays for contractor and permanent staff, with duplicate-scan protection at shift change.', 'High-volume gates'], ['Educational Institutions', 'Teaching and admin staff punch at the staffroom display; reports export straight to Excel.', 'Simple exports'], ['Healthcare Organizations', 'Hygienic, touch-free attendance for clinical staff across multiple clinics.', 'Touch-free'], ['Field Workforce Businesses', 'Approved field and client-site permissions let teams check in remotely with an auditable trail.', 'Remote permissions']];

    public const FAQS = [['Do we need biometric hardware?', 'No. QRONEX needs a screen with a web browser at the entrance and the QRONEX app on employee phones.'], ['Can we use an Android tablet?', 'Yes. Any reasonably recent Android tablet with Chrome can run the display page. A wall mount and permanent power are recommended.'], ['Can we display the QR on a television?', 'Yes. A smart TV browser or a TV connected to a small PC or streaming stick with a browser works, as long as it stays online.'], ['Is office Wi-Fi required?', 'No. The display needs an internet connection (Wi-Fi or a SIM-based tablet), and employees can use mobile data.'], ['Can employees use mobile data?', 'Yes. Punches are small requests and work over normal 4G/5G data.'], ['Can employees mark attendance from home?', 'Only when an administrator approves a remote permission (work from home, field duty, client site) for specific dates. Optionally, a home location and radius can be required.'], ['What happens when GPS is inaccurate?', 'Each punch reports location accuracy. If accuracy is worse than your policy (for example ±50 m), the punch is rejected with a clear reason and the employee can retry near a window or outdoors.'], ['Can an employee register multiple devices?', 'By default one active device per employee. Administrators can raise the limit and approve device replacements.'], ['Can we download attendance in Excel?', 'Yes. The ledger and reports export to CSV, which opens directly in Excel.'], ['Can we connect our existing HRMS?', 'Yes, through scoped REST APIs and signed webhooks. Integration keys can read verified attendance but cannot create verified punches.'], ['Can the QR be opened outside the office?', 'The display URL can open anywhere, but a scan is only accepted after the server checks the token time window, the employee, the approved device and the geofence. These checks reduce misuse; they do not prove presence with certainty.'], ['What happens if internet connectivity fails?', 'The display shows an offline indicator and stops issuing codes. Employees cannot punch until the display or the phone reconnects; missed punches can be added later as approved corrections.']];

    public const LEGAL = [
        'privacy' => ['Privacy Policy'],
        'terms' => ['Terms of Service'],
        'refund' => ['Refund & Cancellation Policy'],
        'retention' => ['Data Retention Policy'],
    ];

    public static function cms(): array
    {
        return array_merge(['hero_title' => 'Attendance without biometric machines.',
            'hero_sub' => 'Turn any screen into a secure QR attendance point. Employees scan, QRONEX verifies, and attendance records are available instantly.',
            'announcement' => null], (array) \App\Models\PlatformSetting::get('cms', []));
    }

    /** Edited in Super Admin → Website; defaults to the prototype FAQ. */
    public static function faqs(): array
    {
        return \App\Models\PlatformSetting::get('cms_faqs') ?: self::FAQS;
    }

    private function plans()
    {
        return Plan::where('is_active', true)->with('currentVersion')->orderBy('sort')->get();
    }

    public function home(Request $request)
    {
        if ($request->user()) {
            return redirect(AuthController::home($request->user()));
        }

        return view('site.home', ['plans' => $this->plans()]);
    }

    public function pricing(Request $request, PriceCalculator $calc)
    {
        $employees = min(max((int) $request->query('employees', 40), 1), 100000);
        $cycle = in_array($request->query('cycle'), ['monthly', 'quarterly', 'annual'], true) ? $request->query('cycle') : 'monthly';
        $plan = Plan::forEmployees($employees);

        return view('site.pricing', ['plans' => $this->plans(), 'employees' => $employees, 'cycle' => $cycle, 'plan' => $plan,
            'quote' => $plan ? $calc->quote($plan->currentVersion, $employees, $cycle) : null]);
    }

    public function contact()
    {
        return view('site.contact');
    }

    /** Public enquiry → support ticket (Super Admin → Support). Honeypot field `website` catches simple bots. */
    public function enquire(Request $request)
    {
        if (filled($request->input('website'))) {
            return back()->with('ok', 'Thanks — we will be in touch.');
        }
        $d = $request->validate([
            'topic' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(self::TOPICS))],
            'name' => 'required|string|max:120', 'email' => 'required|email|max:190',
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+()\-\s]{7,20}$/'],
            'company' => 'nullable|string|max:150', 'employees' => 'nullable|integer|min:1|max:100000', 'message' => 'required|string|max:3000',
        ], [
            'employees.integer' => 'Enter your team size as a number, for example 40.',
            'employees.max' => 'That looks like a phone number. Enter how many employees you have, for example 40.',
            'employees.min' => 'Enter at least 1 employee.',
            'phone.regex' => 'Enter a phone number using digits, spaces, + or brackets.',
            'message.required' => 'Tell us a little about what you need.',
        ]);
        $topic = self::TOPICS[$d['topic'] ?? 'other'] ?? 'Other';
        $t = SupportTicket::create(['name' => $d['name'], 'email' => $d['email'], 'phone' => $d['phone'] ?? null, 'area' => 'sales',
            'subject' => "Website enquiry ({$topic})".(isset($d['company']) ? " — {$d['company']}" : '').(isset($d['employees']) ? " · {$d['employees']} employees" : '')]);
        $t->messages()->create(['body' => $d['message']]);
        HelpController::notifyStaff($t, $d['message']);

        return back()->with('ok', "Thanks, {$d['name']}. We will reply to {$d['email']} within one working day (ref {$t->ref}).");
    }

    public function legal(string $page)
    {
        abort_unless(isset(self::LEGAL[$page]), 404);

        return view('site.legal', ['page' => $page, 'title' => self::LEGAL[$page][0]]);
    }
}
