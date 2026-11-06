<?php

namespace Database\Seeders;

use App\Models\MessageTemplate;
use App\Models\Plan;
use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

/** Reference data every environment needs: platform settings, plans (v1), message templates. Safe to re-run. */
class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PlatformSetting::DEFAULTS as $k => $v) {
            if (! PlatformSetting::find($k)) {
                PlatformSetting::put($k, $v);
            }
        }

        $this->templates();

        // Demonstration prices — NOT approved by the founder yet.
        $plans = [
            ['starter', 'Starter', 'Single office, small teams', 1, 25, false, ['per_employee', 49, 0, 499, 5, 15, 2999, 0, 14, false, 100]],
            ['growth', 'Growth', 'Multiple offices and API access', 26, 150, true, ['per_employee', 39, 0, 1499, 5, 15, 4999, 25, 14, true, 500]],
            ['scale', 'Scale', 'Larger teams, fixed monthly slab', 151, 500, false, ['slab', 0, 11999, 0, 7, 18, 9999, 15, 14, true, 2000]],
            ['enterprise', 'Enterprise', 'Negotiated contract', 501, 100000, false, ['custom', 0, 0, 0, 0, 0, 0, 0, 30, true, 10000]],
        ];
        foreach ($plans as $i => [$code, $name, $desc, $min, $max, $popular, $p]) {
            if (Plan::where('code', $code)->exists()) {
                continue; // never touch live prices on a re-run
            }
            $plan = Plan::create(['code' => $code, 'name' => $name, 'description' => $desc, 'min_employees' => $min, 'max_employees' => $max, 'is_popular' => $popular, 'sort' => $i]);
            $plan->versions()->create(array_combine(
                ['pricing_model', 'per_employee_price', 'slab_price', 'min_billing', 'quarterly_discount_pct', 'annual_discount_pct', 'onboarding_fixed', 'onboarding_per_employee', 'trial_days', 'api_included', 'sms_allowance'], $p
            ) + ['version' => 1, 'note' => 'Initial demonstration pricing', 'created_by' => null]);
        }
    }

    /** Editable in Super Admin → Email & SMS. SMS bodies are placeholders until DLT approval. */
    private function templates(): void
    {
        $email = [
            ['registration', 'Registration confirmation', 'Welcome to QRONEX, {{company_name}}', "Hi {{admin_name}},\n\n{{company_name}} is now registered on QRONEX. Finish setup to start capturing attendance.\n\nTeam QRONEX"],
            ['welcome', 'Welcome message', 'Your QRONEX workspace is ready', "Hi {{admin_name}},\n\nYour {{subscription_plan}} subscription is active.\n\nTeam QRONEX"],
            ['reset', 'Password reset', 'Reset your QRONEX password', "Use this link to reset your password (valid 60 minutes):\n{{reset_link}}\n\nIf you did not request this, ignore this email."],
            ['activation', 'App activation', 'Activate the QRONEX attendance app', "Hi {{employee_name}},\n\n{{company_name}} uses QRONEX for attendance. Install the QRONEX app and enter:\n\nCompany code: {{company_code}}\nEmployee code: {{employee_code}}\nActivation code: {{activation_code}}\n\nThe code works once and expires in {{hours}} hours."],
            ['dev_ok', 'Device approval', 'Your phone is approved for attendance', "Hi {{employee_name}},\n\nYour {{device_model}} is approved. You can now scan QR codes at {{company_name}}."],
            ['dev_no', 'Device rejection', 'Device registration not approved', "Hi {{employee_name}},\n\nYour device registration was not approved. Contact your administrator."],
            ['trial', 'Trial expiry', 'Your QRONEX trial ends in {{days_left}} days', "Hi {{admin_name}},\n\nChoose a plan to keep your attendance records flowing."],
            ['pay_ok', 'Payment successful', 'Payment received — {{invoice_number}}', "We received {{amount}} for {{subscription_plan}}. Thank you."],
            ['pay_fail', 'Payment failed', 'Payment failed for {{company_name}}', "Your payment of {{amount}} did not go through. Retry from Billing to avoid interruption."],
            ['invoice', 'Invoice issued', 'Invoice {{invoice_number}} from QRONEX', "Your invoice {{invoice_number}} for {{amount}} is available under Subscription & billing."],
            ['renewal', 'Subscription renewal', 'Your QRONEX subscription needs attention', "Hi {{admin_name}},\n\n{{subscription_plan}} for {{company_name}} ended on {{renewal_date}}. Pay from Billing to keep access."],
            ['verify', 'Email verification', 'Your QRONEX verification code', "Your verification code is {{otp}}. It expires in 30 minutes."],
            ['dev_request', 'Device waiting for approval', '{{request_type}} waiting for approval — {{employee_name}}', "Hi {{admin_name}},\n\n{{employee_name}} registered a {{device_model}} ({{request_type}}). Review it: {{approvals_url}}"],
            ['remote_request', 'Remote attendance request', 'Remote request from {{employee_name}}', "Hi {{admin_name}},\n\n{{employee_name}} asked for {{permission_type}} from {{from_date}} to {{to_date}}.\nReason: {{reason}}\n\nReview it: {{approvals_url}}"],
            ['correction_request', 'Punch correction request', 'Punch correction from {{employee_name}}', "Hi {{admin_name}},\n\n{{employee_name}} asked to add a missed punch: {{punch}}.\nReason: {{reason}}\n\nReview it: {{approvals_url}}"],
            ['remote_ok', 'Remote attendance approval', 'Remote attendance approved', "Hi {{employee_name}},\n\n{{permission_type}} is approved from {{from_date}} to {{to_date}}. Use Remote check-in in the app."],
            ['remote_no', 'Remote attendance rejection', 'Remote attendance request not approved', "Hi {{employee_name}},\n\nYour {{permission_type}} request was not approved. Contact your manager."],
            ['daily_report', 'Daily punch register', 'QRONEX register for {{date}} — {{company_name}}', "Attached: {{punches}} verified punches by {{employees}} employees on {{date}}."],
            ['ticket_staff', 'New support message (staff)', '[{{ticket}}] {{subject}}', "From {{from}}:\n\n{{body}}"],
            ['ticket_reply', 'Support reply', 'Re: [{{ticket}}] {{subject}}', "Hi {{name}},\n\n{{body}}\n\n— QRONEX support"],
            ['security', 'Security alert', 'Security alert on your QRONEX account', "We noticed {{event}} at {{time}}. If this was not you, contact your administrator."],
        ];
        foreach ($email as [$key, $name, $subject, $body]) {
            MessageTemplate::firstOrCreate(['channel' => 'email', 'key' => $key], ['name' => $name, 'subject' => $subject, 'body' => $body]);
        }
        foreach ([
            ['otp', 'OTP verification', '1207XXXXXXXXXXXX001', 'Your QRONEX verification code is {#var#}. Valid for {#var#} minutes. Do not share it. -QRONEX'],
            ['activate', 'Device activation', '1207XXXXXXXXXXXX002', 'Activate QRONEX attendance for {#var#}: install the app and sign in with code {#var#}. -QRONEX'],
            ['security', 'Security confirmation', '1207XXXXXXXXXXXX003', 'A new device was registered on your QRONEX account at {#var#}. Not you? Contact your admin. -QRONEX'],
            ['payrem', 'Payment reminder', '1207XXXXXXXXXXXX004', 'QRONEX subscription for {#var#} is due on {#var#}. Pay from Billing to avoid interruption. -QRONEX'],
        ] as [$key, $name, $dlt, $body]) {
            MessageTemplate::firstOrCreate(['channel' => 'sms', 'key' => $key], ['name' => $name, 'dlt_template_id' => $dlt, 'body' => $body]);
        }
    }
}
