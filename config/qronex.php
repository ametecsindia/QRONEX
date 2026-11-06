<?php

return [
    // Supplier (for tax invoices). Fill real values before going live.
    'supplier' => [
        'name' => env('QRONEX_SUPPLIER_NAME', 'Ametecs India Private Limited'),
        'gstin' => env('QRONEX_SUPPLIER_GSTIN', '36XXXXXXXXXXXZX'),
        'state' => env('QRONEX_SUPPLIER_STATE', 'Telangana'),
        'address' => env('QRONEX_SUPPLIER_ADDRESS', 'Kondapur, Hyderabad, Telangana'),
        'email' => env('QRONEX_SUPPORT_EMAIL', 'support@qronex.app'),
    ],
    'invoice_prefix' => env('QRONEX_INVOICE_PREFIX', 'QRX'),
    'grace_days' => (int) env('QRONEX_GRACE_DAYS', 7),
    'activation_hours' => 72,
    'require_admin_2fa' => (bool) env('QRONEX_REQUIRE_ADMIN_2FA', env('APP_ENV') === 'production'),

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    // driver: log (writes to message_logs only) | http (generic DLT gateway; adapt payload in Notifier::sendSms)
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'url' => env('SMS_URL'),
        'api_key' => env('SMS_API_KEY'),
        'sender' => env('SMS_SENDER', 'QRONEX'),
        'pe_id' => env('SMS_DLT_PE_ID'),
    ],
];
