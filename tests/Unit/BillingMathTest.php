<?php

namespace Tests\Unit;

use App\Models\MessageTemplate;
use App\Services\AttendanceVerifier;
use App\Services\Billing;
use App\Services\Razorpay;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class BillingMathTest extends TestCase
{
    public function test_gst_split_intra_vs_inter_state(): void
    {
        $this->assertSame(['cgst' => 680.5, 'sgst' => 680.5, 'igst' => 0.0], Billing::gstSplit(1361, 'Telangana', 'Telangana'));
        $this->assertSame(['cgst' => 0.0, 'sgst' => 0.0, 'igst' => 1361.0], Billing::gstSplit(1361, 'Karnataka', 'Telangana'));
        $this->assertSame(0.0, Billing::gstSplit(1361, null, 'Telangana')['igst']);
    }

    public function test_financial_year_label(): void
    {
        $this->assertSame('26-27', Billing::financialYear(Carbon::parse('2026-11-05')));
        $this->assertSame('25-26', Billing::financialYear(Carbon::parse('2026-03-31')));
        $this->assertSame('99-00', Billing::financialYear(Carbon::parse('2099-04-01')));
    }

    public function test_razorpay_signatures(): void
    {
        $sig = hash_hmac('sha256', 'order_1|pay_1', 'sk');
        $this->assertTrue(Razorpay::validPaymentSignature('order_1', 'pay_1', $sig, 'sk'));
        $this->assertFalse(Razorpay::validPaymentSignature('order_1', 'pay_2', $sig, 'sk'));
        $this->assertTrue(Razorpay::validWebhookSignature('{"a":1}', hash_hmac('sha256', '{"a":1}', 'wh'), 'wh'));
        $this->assertFalse(Razorpay::validWebhookSignature('{"a":1}', 'x', ''), 'empty secret never validates');
    }

    public function test_template_rendering(): void
    {
        $this->assertSame('Hi Ravi, ₹10', MessageTemplate::render('Hi {{ name }}, {{amount}}', ['name' => 'Ravi', 'amount' => '₹10']));
        $this->assertSame('Code 1234 for 5 min', MessageTemplate::render('Code {#var#} for {#var#} min', ['1234', '5']));
    }

    public function test_haversine_distance(): void
    {
        // Hyderabad HQ → ~1.83 km north (prototype "outside" case)
        $d = AttendanceVerifier::metres(17.44862, 78.39081, 17.46502, 78.39281);
        $this->assertEqualsWithDelta(1834, $d, 15);
    }
}
