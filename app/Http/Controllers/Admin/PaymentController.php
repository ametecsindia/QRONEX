<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Services\Razorpay;

class PaymentController extends Controller
{
    public function __invoke()
    {
        return view('admin.payments', [
            'payments' => Payment::with('invoice')->latest()->paginate(50),
            'events' => PaymentEvent::latest('created_at')->take(20)->get(),
            'tenants' => \App\Models\Tenant::pluck('name', 'id'),
            'mode' => Razorpay::mode(),
            'webhook' => filled(config('qronex.razorpay.webhook_secret')),
            'stats' => [
                'month' => (float) \App\Models\Invoice::where('status', 'paid')->where('issued_on', '>=', now('Asia/Kolkata')->startOfMonth()->toDateString())->sum('total'),
                'fy' => (float) \App\Models\Invoice::where('status', 'paid')->where('issued_on', '>=', (now('Asia/Kolkata')->month >= 4 ? now('Asia/Kolkata')->year : now('Asia/Kolkata')->year - 1).'-04-01')->sum('total'),
                'gst_fy' => (float) \App\Models\Invoice::where('status', 'paid')->where('issued_on', '>=', (now('Asia/Kolkata')->month >= 4 ? now('Asia/Kolkata')->year : now('Asia/Kolkata')->year - 1).'-04-01')->selectRaw('sum(cgst + sgst + igst) t')->value('t'),
                'failed30' => Payment::where('status', 'failed')->where('created_at', '>=', now()->subDays(30))->count(),
                'due' => \App\Models\Tenant::where('status', 'grace')->count(),
            ],
        ]);
    }
}
