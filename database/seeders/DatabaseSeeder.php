<?php

namespace Database\Seeders;

use App\Models\AttendanceEvent;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Office;
use App\Models\PlanVersion;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Mirrors the Stage 1 prototype demo data. All data is simulated. Dev password for every seeded user (and the demo app activation code): 12345678 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $pw = '12345678';

        if (app()->isProduction()) {
            throw new \RuntimeException('DatabaseSeeder loads DEMO data with a shared password. In production run: php artisan db:seed --class=ProductionSeeder');
        }
        $this->call(PlatformSeeder::class);

        $admin = User::create(['name' => 'QRONEX Super Admin', 'email' => 'admin@qronex.test', 'password' => $pw, 'role' => 'super_admin']);
        User::create(['name' => 'QRONEX Support', 'email' => 'support@qronex.test', 'password' => $pw, 'role' => 'support_admin']);

        $versions = PlanVersion::with('plan')->get()->keyBy(fn ($v) => $v->plan->code);

        $t = Tenant::create([
            'name' => 'Storm Financial Services Pvt Ltd', 'slug' => 'stormfinancial', 'org_type' => 'Private Limited',
            'industry' => 'Debt Collection Agency', 'state' => 'Telangana', 'city' => 'Hyderabad',
            'address' => 'Plot 21, Hitech City Road, Madhapur, Hyderabad 500081', 'gstin' => '36AABCS1234Q1Z9',
            'website' => 'stormfinancial.example', 'status' => 'active', 'customer_type' => 'independent',
        ]);
        TenantSetting::create(['tenant_id' => $t->id] + TenantSetting::defaults());

        $offices = [];
        foreach ([
            ['HYD01', 'hyderabad', 'Hyderabad HQ', 'Hitech City, Madhapur', 'Hyderabad', 'Telangana', 17.44862, 78.39081, 150, [['gate1', 'Main Entrance'], ['gate2', 'Floor 4 Lobby']]],
            ['VJA01', 'vijayawada', 'Vijayawada Branch', 'Benz Circle', 'Vijayawada', 'Andhra Pradesh', 16.49962, 80.65611, 120, [['gate1', 'Main Entrance']]],
            ['BLR01', 'bengaluru', 'Bengaluru Office', 'Koramangala 5th Block', 'Bengaluru', 'Karnataka', 12.93521, 77.62448, 200, [['gate1', 'Reception']]],
        ] as [$code, $slug, $name, $addr, $city, $state, $lat, $lng, $r, $gates]) {
            $o = Office::create(['tenant_id' => $t->id, 'code' => $code, 'slug' => $slug, 'name' => $name, 'address' => $addr, 'city' => $city, 'state' => $state, 'lat' => $lat, 'lng' => $lng, 'geofence_radius_m' => $r]);
            foreach ($gates as [$gc, $gn]) {
                $o->gates()->create(['tenant_id' => $t->id, 'code' => $gc, 'name' => $gn]);
            }
            $offices[$code] = $o;
        }

        $owner = User::create(['tenant_id' => $t->id, 'name' => 'Anil Mathur', 'email' => 'anil.mathur@stormfinancial.example', 'password' => $pw, 'role' => 'company_owner']);
        User::create(['tenant_id' => $t->id, 'name' => 'Rekha Srinivas', 'email' => 'rekha.s@stormfinancial.example', 'password' => $pw, 'role' => 'company_admin']);
        User::create(['tenant_id' => $t->id, 'name' => 'Prakash Yadav', 'email' => 'prakash.y@stormfinancial.example', 'password' => $pw, 'role' => 'office_manager'])->offices()->attach($offices['VJA01']);
        User::create(['tenant_id' => $t->id, 'name' => 'Nandini Rao', 'email' => 'nandini.r@stormfinancial.example', 'password' => $pw, 'role' => 'attendance_approver'])->offices()->attach($offices['HYD01']);

        $depts = ['Collections' => ['Collection Executive', 'Team Leader'], 'Tele-calling' => ['Tele-caller', 'Senior Tele-caller'], 'Legal' => ['Legal Associate'], 'Operations' => ['Operations Executive', 'Branch Manager'], 'HR' => ['HR Executive'], 'IT' => ['System Administrator'], 'Quality' => ['Quality Analyst']];
        $dk = array_keys($depts);
        $models = ['android' => ['Samsung Galaxy A35', 'Redmi Note 13', 'Vivo Y28', 'OnePlus Nord CE4', 'Realme 12', 'Motorola G64'], 'ios' => ['iPhone 13', 'iPhone 15']];
        $devStat = ['EMP0007' => 'pending', 'EMP0012' => 'pending', 'EMP0016' => null, 'EMP0019' => 'revoked', 'EMP0022' => 'rejected'];
        $names = ['Aarav Sharma', 'Priya Reddy', 'Mohammed Irfan', 'Sneha Kulkarni', 'Rahul Varma', 'Ananya Iyer', 'Kiran Kumar', 'Fatima Begum', 'Vikram Rao', 'Divya Nair', 'Sai Teja', 'Nikhil Joshi', 'Harini Rao', 'Arjun Patel', 'Lavanya Gupta', 'Syed Ameer', 'Pooja Menon', 'Rohit Singh', 'Meghana Das', 'Abdul Rahman', 'Keerthi Naidu', 'Tarun Bose', 'Swathi Prasad', 'Imran Khan'];

        foreach ($names as $i => $n) {
            $code = sprintf('EMP%04d', $i + 1);
            $d = $dk[$i % count($dk)];
            $e = Employee::create([
                'tenant_id' => $t->id, 'office_id' => $offices[$i < 14 ? 'HYD01' : ($i < 20 ? 'VJA01' : 'BLR01')]->id,
                'employee_code' => $code, 'hrms_ref' => 'HR-'.(5100 + $i * 7), 'name' => $n,
                'email' => strtolower(str_replace(' ', '.', $n)).'@stormfinancial.example', 'mobile' => '+9198'.str_pad((string) (48213 * ($i + 7) % 100000000), 8, '0', STR_PAD_LEFT),
                'department' => $d, 'designation' => $depts[$d][$i % count($depts[$d])], 'status' => $i === 18 ? 'inactive' : 'active',
            ]);

            $st = array_key_exists($code, $devStat) ? $devStat[$code] : 'approved';
            if (! $st) {
                continue;
            }
            $pf = $i % 6 === 5 ? 'ios' : 'android';
            Device::create([
                'tenant_id' => $t->id, 'employee_id' => $e->id, 'platform' => $pf, 'model' => $models[$pf][$i % count($models[$pf])],
                'os_version' => $pf === 'ios' ? 'iOS 18.'.($i % 4) : 'Android '.(13 + $i % 3), 'install_ref' => 'inst_'.substr(md5($code.'i'), 0, 10),
                'status' => $st, 'approved_by' => $st === 'approved' ? $owner->id : null, 'approved_at' => $st === 'approved' ? now()->subDays(100 - $i) : null,
                'last_seen_at' => now()->subHours($i + 1), 'created_at' => now()->subDays($st === 'pending' ? 1 : 100 - $i),
            ]);
        }

        $sub = Subscription::create([
            'tenant_id' => $t->id, 'plan_version_id' => $versions['growth']->id, 'billing_cycle' => 'monthly', 'employee_count' => 24,
            'status' => 'active', 'current_period_start' => now()->subDays(1)->toDateString(), 'current_period_end' => now()->addDays(29)->toDateString(),
        ]);

        // Same as prototype: 3 paid monthly invoices; the first carries onboarding. Intra-state (Telangana) → CGST + SGST.
        foreach ([[-90, 41, true], [-60, 58, false], [-30, 75, false]] as [$days, $no, $onboard]) {
            $subtotal = 39 * 24;
            $ob = $onboard ? 4999 + 25 * 24 : 0;
            $gst = (int) round(($subtotal + $ob) * 0.18);
            Invoice::create([
                'tenant_id' => $t->id, 'subscription_id' => $sub->id, 'number' => sprintf('QRX/26-27/%04d', $no), 'issued_on' => now()->addDays($days)->toDateString(),
                'description' => 'Growth plan · monthly · 24 employees', 'subtotal' => $subtotal, 'onboarding' => $ob, 'taxable' => $subtotal + $ob,
                'gst_rate' => 18, 'cgst' => $gst / 2, 'sgst' => $gst / 2, 'igst' => 0, 'total' => $subtotal + $ob + $gst,
                'status' => 'paid', 'payment_ref' => 'pay_SIM'.substr(md5("p$no"), 0, 10),
            ]);
        }

        $this->attendance($t->id);
        $this->requests($t->id, $owner->id);

        AuditLog::create(['tenant_id' => $t->id, 'user_id' => $owner->id, 'action' => 'seed', 'subject' => 'Demo data loaded', 'ip' => '127.0.0.1']);

        $this->demoCompany($pw, $versions['starter'] ?? $versions->first());
        $this->deviceHistory();
    }

    /** Match codes for waiting phones + a believable history for every seeded phone. */
    private function deviceHistory(): void
    {
        foreach (Device::withoutGlobalScopes()->get() as $d) {
            if ($d->status === 'pending') {
                $d->forceFill(['approval_code' => Device::newApprovalCode()])->save();
            }
            $rows = [['action' => $d->replaces_device_id ? 'replacement_requested' : 'registered', 'actor_user_id' => null, 'reason' => null, 'created_at' => $d->created_at]];
            if (in_array($d->status, ['approved', 'revoked'], true)) {
                $rows[] = ['action' => 'approved', 'actor_user_id' => $d->approved_by, 'reason' => null, 'created_at' => $d->approved_at ?? $d->created_at];
            }
            if ($d->status === 'revoked') {
                $rows[] = ['action' => 'revoked', 'actor_user_id' => $d->approved_by, 'reason' => 'Employee left the company', 'created_at' => now()->subDays(3)];
            }
            if ($d->status === 'rejected') {
                $rows[] = ['action' => 'rejected', 'actor_user_id' => null, 'reason' => 'Personal phone not allowed — use the company phone', 'created_at' => $d->created_at->copy()->addHours(2)];
            }
            foreach ($rows as $r) {
                \App\Models\DeviceEvent::create($r + ['tenant_id' => $d->tenant_id, 'device_id' => $d->id, 'meta' => ['model' => $d->model, 'ip' => '10.0.0.'.($d->id % 250)]]);
            }
        }
    }

    /**
     * Simple company for trying the app: company code "demo", app activation code 12345678 for DEMO001–DEMO003,
     * no device approval needed, office geofence covers the whole planet so a punch passes from anywhere (emulator included).
     */
    private function demoCompany(string $pw, PlanVersion $version): void
    {
        $t = Tenant::create(['name' => 'QRONEX Demo Company', 'slug' => 'demo', 'org_type' => 'Private Limited', 'industry' => 'Demo',
            'state' => 'Telangana', 'city' => 'Hyderabad', 'address' => 'Kondapur, Hyderabad', 'status' => 'active']);
        TenantSetting::create(['tenant_id' => $t->id, 'require_device_approval' => false, 'gps_accuracy_m' => 150] + TenantSetting::defaults()); // demo: works indoors
        // ponytail: 20,000 km radius = demo-only "anywhere" geofence; set a real 150 m radius in Portal → Offices to test location checks.
        $o = Office::create(['tenant_id' => $t->id, 'code' => 'DEMO01', 'slug' => 'office', 'name' => 'Demo Office', 'address' => 'Kondapur',
            'city' => 'Hyderabad', 'state' => 'Telangana', 'lat' => 17.46356, 'lng' => 78.36245, 'geofence_radius_m' => 20000000]);
        $o->gates()->create(['tenant_id' => $t->id, 'code' => 'gate1', 'name' => 'Main Entrance']);

        $owner = User::create(['tenant_id' => $t->id, 'name' => 'Demo Owner', 'email' => 'demo@qronex.test', 'password' => $pw, 'role' => 'company_owner']);
        User::create(['tenant_id' => $t->id, 'name' => 'Demo Admin', 'email' => 'demoadmin@qronex.test', 'password' => $pw, 'role' => 'company_admin']);

        foreach (['Ravi Kumar', 'Sana Fatima', 'Arjun Reddy', 'Meera Joshi', 'Kiran Rao', 'Farhan Ali', 'Divya Shetty', 'Rohit Varma'] as $i => $n) {
            $e = Employee::create(['tenant_id' => $t->id, 'office_id' => $o->id, 'employee_code' => sprintf('DEMO%03d', $i + 1), 'name' => $n,
                'email' => strtolower(strtok($n, ' ')).'@demo.example', 'mobile' => '+91900000000'.($i + 1), 'department' => $i % 2 ? 'Collections' : 'Operations',
                'designation' => $i % 2 ? 'Collection Executive' : 'Operations Executive', 'status' => 'active']);
            if ($i < 3) { // free for the app: activate with 12345678
                $e->forceFill(['activation_code_hash' => \Illuminate\Support\Facades\Hash::make($pw), 'activation_expires_at' => now()->addYear()])->save();
                continue;
            }
            Device::create(['tenant_id' => $t->id, 'employee_id' => $e->id, 'platform' => 'android', 'model' => 'Redmi Note 13', 'os_version' => 'Android 14',
                'install_ref' => 'inst_demo_'.$i, 'status' => 'approved', 'approved_by' => $owner->id, 'approved_at' => now()->subDays(30), 'last_seen_at' => now()->subHour()]);
        }

        Subscription::create(['tenant_id' => $t->id, 'plan_version_id' => $version->id, 'billing_cycle' => 'monthly', 'employee_count' => 8, 'status' => 'active',
            'current_period_start' => now()->subDays(5)->toDateString(), 'current_period_end' => now()->addDays(25)->toDateString()]);
        $this->attendance($t->id, 5001);
    }

    /** Prototype requests: 2 approved remote permissions (one waived), 1 pending, 1 correction, 1 replacement phone, tickets. */
    private function requests(int $tid, int $ownerId): void
    {
        $emp = fn (string $code) => Employee::withoutGlobalScopes()->where(['tenant_id' => $tid, 'employee_code' => $code])->first();
        $today = today('Asia/Kolkata');
        foreach ([
            ['RP-1001', 'EMP0010', 'wfh', -3, 4, 'Home — Banjara Hills', 17.41503, 78.43478, 300, false, 'Family relocation week — approved by reporting manager', 'approved', 'portal'],
            ['RP-1002', 'EMP0015', 'field_duty', 0, 1, null, null, null, null, true, 'Branch audits in Guntur and Tenali', 'approved', 'portal'],
            ['RP-1003', 'EMP0004', 'wfh', 0, 1, 'Home — Kukatpally', 17.49317, 78.39913, 250, false, 'Broadband technician visit', 'pending', 'app'],
        ] as [$ref, $code, $type, $from, $to, $label, $lat, $lng, $r, $waived, $reason, $status, $via]) {
            \App\Models\RemotePermission::create(['ref' => $ref, 'tenant_id' => $tid, 'employee_id' => $emp($code)->id, 'type' => $type,
                'from_date' => $today->copy()->addDays($from)->toDateString(), 'to_date' => $today->copy()->addDays($to)->toDateString(),
                'location_label' => $label, 'lat' => $lat, 'lng' => $lng, 'radius_m' => $r, 'location_waived' => $waived, 'reason' => $reason,
                'status' => $status, 'requested_via' => $via, 'decided_by' => $status === 'approved' ? $ownerId : null, 'decided_at' => $status === 'approved' ? now()->subDays(4) : null]);
        }
        \App\Models\PunchCorrection::create(['ref' => 'COR-208', 'tenant_id' => $tid, 'employee_id' => $emp('EMP0009')->id, 'type' => 'OUT',
            'punch_at' => $today->copy()->subDay()->setTime(18, 40)->utc(), 'reason' => 'Forgot to punch OUT — left with the Vijayawada audit team', 'requested_via' => 'app']);

        $e3 = $emp('EMP0003');
        $old = Device::withoutGlobalScopes()->where('employee_id', $e3->id)->where('status', 'approved')->first();
        Device::create(['tenant_id' => $tid, 'employee_id' => $e3->id, 'platform' => 'android', 'model' => 'Samsung Galaxy S24', 'os_version' => 'Android 15',
            'install_ref' => 'inst_'.substr(md5('rpl31'), 0, 10), 'status' => 'pending', 'replaces_device_id' => $old?->id, 'created_at' => now()->subHours(2)]);

        $t1 = \App\Models\SupportTicket::create(['ref' => 'T-1042', 'tenant_id' => $tid, 'user_id' => $ownerId, 'name' => 'Anil Mathur', 'email' => 'anil.mathur@stormfinancial.example',
            'subject' => 'Floor 4 lobby tablet keeps going offline', 'area' => 'display', 'priority' => 'normal']);
        $t1->messages()->create(['user_id' => $ownerId, 'body' => 'The display shows offline after about 40 minutes. It is a Lenovo Tab on Wi-Fi.']);
        $t2 = \App\Models\SupportTicket::create(['ref' => 'T-1039', 'name' => 'Website visitor', 'email' => 'ops@factory.example', 'subject' => 'Website enquiry — 300 employees', 'area' => 'sales']);
        $t2->messages()->create(['body' => 'Pricing for 3 factories with shift work?']);
    }

    /** Last 6 working days: IN ≈ 09:00, OUT ≈ 18:15 IST at the employee's office, for approved devices. Deterministic. */
    private function attendance(int $tenantId, int $n = 1201): void
    {
        mt_srand(20261105);
        $employees = Employee::withoutGlobalScopes()->with(['office.gates', 'devices' => fn ($q) => $q->where('status', 'approved')])->where('tenant_id', $tenantId)->where('status', 'active')->get();
        $rows = [];
        for ($k = 6; $k >= 0; $k--) { // k = 0: today, only punches already in the past
            $day = today('Asia/Kolkata')->subDays($k);
            if ($day->isSunday()) {
                continue;
            }
            foreach ($employees as $e) {
                $dev = $e->devices->first();
                if (! $dev || mt_rand(1, 100) <= 7) {
                    continue; // ~7% absent
                }
                $gate = $e->office->gates->first();
                foreach (['IN' => [9, 0, -30, 45], 'OUT' => [18, 15, -20, 60]] as $type => [$h, $m, $lo, $hi]) {
                    $at = $day->copy()->setTime($h, $m)->addMinutes(mt_rand($lo, $hi))->addSeconds(mt_rand(0, 59))->utc();
                    if ($at->isFuture()) {
                        continue;
                    }
                    $dist = mt_rand(8, (int) (min($e->office->geofence_radius_m, 200) * 0.6));
                    $rows[] = [
                        'ref' => 'QRX-'.str_pad((string) $n++, 8, '0', STR_PAD_LEFT), 'tenant_id' => $tenantId, 'employee_id' => $e->id, 'device_id' => $dev->id,
                        'office_id' => $e->office_id, 'gate_id' => $gate->id, 'type' => $type, 'mode' => 'OFFICE_QR', 'qr_window' => intdiv($at->timestamp, 3),
                        'lat' => $e->office->lat, 'lng' => $e->office->lng, 'accuracy_m' => mt_rand(6, 25), 'distance_m' => $dist, 'recorded_at' => $at, 'created_at' => $at,
                    ];
                }
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            AttendanceEvent::insert($chunk);
        }

        // a few rejected scans so the dashboards show the reasons view
        $reasons = [['OUTSIDE_GEOFENCE', 'You are outside the office geofence'], ['QR_EXPIRED', 'QR code expired — scan the live code'], ['LOW_GPS_ACCURACY', 'Location accuracy too low'], ['DEVICE_NOT_APPROVED', 'This phone is not approved yet'], ['DUPLICATE', 'Already punched a moment ago']];
        $rej = [];
        foreach ($employees->random(min(9, $employees->count())) as $i => $e) {
            [$code, $why] = $reasons[$i % count($reasons)];
            $rej[] = ['ref' => 'REJ-'.$tenantId.'-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'tenant_id' => $tenantId, 'employee_id' => $e->id, 'device_id' => $e->devices->first()?->id,
                'gate_id' => $e->office->gates->first()?->id, 'reason_code' => $code, 'reason' => $why, 'created_at' => now()->subHours(mt_rand(2, 140))];
        }
        \App\Models\AttendanceRejection::insert($rej);
    }

}
