<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DisplayController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\Portal;
use App\Http\Middleware\EnsureRole;
use Illuminate\Support\Facades\Route;

/* Public website */
Route::get('/', [\App\Http\Controllers\SiteController::class, 'home'])->name('home');
Route::get('pricing', [\App\Http\Controllers\SiteController::class, 'pricing'])->name('pricing');
Route::get('contact', [\App\Http\Controllers\SiteController::class, 'contact'])->name('contact');
Route::post('contact', [\App\Http\Controllers\SiteController::class, 'enquire'])->middleware('throttle:5,1');
Route::get('legal/{page}', [\App\Http\Controllers\SiteController::class, 'legal'])->name('legal');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::get('forgot-password', [PasswordController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordController::class, 'reset'])->name('password.reset');
    Route::post('reset-password', [PasswordController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});

Route::view('docs/api', 'docs.api')->name('docs.api');
Route::get('health', \App\Http\Controllers\HealthController::class)->middleware('throttle:60,1')->name('health');

/* Entrance QR display (public, protected by the gate's display key) */
Route::get('display/{tenant}/{office}/{gate}', [DisplayController::class, 'show'])->name('display.show');
Route::get('display/{tenant}/{office}/{gate}/token', [DisplayController::class, 'token'])->middleware('throttle:120,1')->name('display.token');
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('guest')->group(function () {
    Route::get('two-factor', [\App\Http\Controllers\TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('two-factor', [\App\Http\Controllers\TwoFactorController::class, 'verify'])->middleware('throttle:6,1');
});

Route::middleware('auth')->prefix('account')->name('account.')->controller(\App\Http\Controllers\TwoFactorController::class)->group(function () {
    Route::get('security', 'show')->name('security');
    Route::get('verify-email', [\App\Http\Controllers\VerifyEmailController::class, 'show'])->name('verify-email');
    Route::post('verify-email', [\App\Http\Controllers\VerifyEmailController::class, 'verify'])->middleware('throttle:6,1');
    Route::post('verify-email/resend', [\App\Http\Controllers\VerifyEmailController::class, 'resend'])->middleware('throttle:3,1')->name('verify-email.resend');
    Route::post('password', 'password')->middleware('throttle:6,1')->name('password');
    Route::post('2fa/enable', 'enable')->name('2fa.enable');
    Route::post('2fa/confirm', 'confirm')->middleware('throttle:6,1')->name('2fa.confirm');
    Route::post('2fa/recovery', 'recovery')->middleware('throttle:6,1')->name('2fa.recovery');
    Route::post('2fa/disable', 'disable')->middleware('throttle:6,1')->name('2fa.disable');
});

/* Super Admin */
Route::prefix('super')->name('admin.')->middleware(['auth', EnsureRole::class.':super_admin,support_admin'])->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::get('companies', [Admin\CompanyController::class, 'index'])->name('companies.index');
    Route::get('payments', Admin\PaymentController::class)->name('payments');
    Route::get('displays', Admin\DisplayMonitorController::class)->name('displays');
    Route::get('api', Admin\ApiUsageController::class)->name('api');
    Route::get('system', [Admin\SystemController::class, 'index'])->name('system');
    Route::get('website', [Admin\WebsiteController::class, 'edit'])->name('website.edit');
    Route::post('website', [Admin\WebsiteController::class, 'update'])->middleware('can:manage-platform')->name('website.update');
    Route::get('support', [Admin\SupportController::class, 'index'])->name('support.index');
    Route::get('support/{ticket}', [Admin\SupportController::class, 'show'])->name('support.show');
    Route::post('support/{ticket}', [Admin\SupportController::class, 'reply'])->name('support.reply');
    Route::post('support/{ticket}/close', [Admin\SupportController::class, 'close'])->name('support.close');
    Route::get('messaging', [Admin\MessagingController::class, 'index'])->name('messaging.index');
    Route::get('audit', Admin\AuditController::class)->name('audit');
    Route::get('users', [Admin\AdminUserController::class, 'index'])->name('users.index');
    Route::get('companies/create', [Admin\CompanyController::class, 'create'])->middleware('can:manage-platform')->name('companies.create');
    Route::get('companies/{tenant}', [Admin\CompanyController::class, 'show'])->name('companies.show');

    Route::middleware('can:manage-platform')->group(function () {
        Route::post('companies', [Admin\CompanyController::class, 'store'])->name('companies.store');
        Route::post('companies/{tenant}/status', [Admin\CompanyController::class, 'status'])->name('companies.status');
        Route::post('companies/{tenant}/terms', [Admin\CompanyController::class, 'terms'])->name('companies.terms');
        Route::get('plans/{plan}/edit', [Admin\PlanController::class, 'edit'])->name('plans.edit');
        Route::post('plans/{plan}', [Admin\PlanController::class, 'update'])->name('plans.update');
        Route::post('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
        Route::post('users', [Admin\AdminUserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [Admin\AdminUserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/reset-2fa', [Admin\AdminUserController::class, 'resetTwoFactor'])->name('users.reset2fa');
        Route::post('system/jobs/{uuid}/retry', [Admin\SystemController::class, 'retry'])->name('system.retry');
        Route::post('system/jobs/{uuid}/forget', [Admin\SystemController::class, 'forget'])->name('system.forget');
        Route::get('messaging/{template}/edit', [Admin\MessagingController::class, 'edit'])->name('messaging.edit');
        Route::put('messaging/{template}', [Admin\MessagingController::class, 'update'])->name('messaging.update');
        Route::post('messaging/mail', [Admin\MessagingController::class, 'mail'])->name('messaging.mail');
        Route::post('messaging/test', [Admin\MessagingController::class, 'test'])->middleware('throttle:10,1')->name('messaging.test');
    });
    Route::get('plans', [Admin\PlanController::class, 'index'])->name('plans.index');
    Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
});

/* Customer portal */
/* Old addresses keep working */
Route::get('admin/{path?}', fn (string $path = '') => redirect('/super'.($path !== '' ? '/'.$path : '')))->where('path', '.*');
Route::get('app/{path?}', fn (\Illuminate\Http\Request $r, string $path = '') => $r->user()->isPlatform() ? redirect()->route('admin.dashboard')
    : redirect('/'.$r->user()->tenant->slug.($path !== '' ? '/'.$path : '')))->where('path', '.*')->middleware('auth');

/* Client portal at qronex.app/{company-slug} — registered last so fixed paths above always win */
Route::prefix('{company}')->where(['company' => '(?!(?:'.implode('|', \App\Models\Tenant::RESERVED_SLUGS).')(?![a-z0-9-]))[a-z0-9]+(?:-[a-z0-9]+)*'])
    ->name('portal.')->middleware(['auth', EnsureRole::class.':company_owner,company_admin,office_manager,attendance_approver', \App\Http\Middleware\CompanyUrl::class.':check'])->group(function () {
    Route::get('/', Portal\DashboardController::class)->name('dashboard');
    Route::get('attendance', [Portal\AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('attendance/export.csv', [Portal\AttendanceController::class, 'export'])->name('attendance.export');
    Route::get('reports', [Portal\ReportController::class, 'index'])->name('reports');
    Route::get('reports/{report}.csv', [Portal\ReportController::class, 'download'])->name('reports.download');
    Route::get('help', [Portal\HelpController::class, 'index'])->name('help.index');
    Route::post('help', [Portal\HelpController::class, 'store'])->middleware('throttle:10,1')->name('help.store');
    Route::get('help/{ticket}', [Portal\HelpController::class, 'show'])->name('help.show');
    Route::post('help/{ticket}', [Portal\HelpController::class, 'reply'])->middleware('throttle:20,1')->name('help.reply');

    Route::middleware('can:manage-billing')->group(function () {
        Route::get('billing', [Portal\BillingController::class, 'index'])->name('billing.index');
        Route::post('billing/checkout', [Portal\BillingController::class, 'checkout'])->middleware('throttle:10,1')->name('billing.checkout');
        Route::post('billing/callback', [Portal\BillingController::class, 'callback'])->name('billing.callback');
        Route::post('billing/{payment}/failed', [Portal\BillingController::class, 'failed'])->name('billing.failed');
        Route::post('billing/{payment}/simulate', [Portal\BillingController::class, 'simulate'])->name('billing.simulate');
        Route::get('invoices/{invoice}', [Portal\BillingController::class, 'invoice'])->name('billing.invoice');
    });

    Route::middleware('can:manage-company')->group(function () {
        Route::get('company', [Portal\CompanyController::class, 'edit'])->name('company.edit');
        Route::put('company', [Portal\CompanyController::class, 'update'])->name('company.update');
        Route::get('policy', [Portal\PolicyController::class, 'edit'])->name('policy.edit');
        Route::get('api', [Portal\ApiController::class, 'index'])->name('api.index');
        Route::post('api/keys', [Portal\ApiController::class, 'storeKey'])->name('api.keys.store');
        Route::post('api/keys/{key}/rotate', [Portal\ApiController::class, 'rotateKey'])->name('api.keys.rotate');
        Route::post('api/keys/{key}/revoke', [Portal\ApiController::class, 'revokeKey'])->name('api.keys.revoke');
        Route::post('api/endpoints', [Portal\ApiController::class, 'storeEndpoint'])->name('api.endpoints.store');
        Route::post('api/endpoints/{endpoint}', [Portal\ApiController::class, 'updateEndpoint'])->name('api.endpoints.update');
        Route::post('api/deliveries/{delivery}/retry', [Portal\ApiController::class, 'redeliver'])->name('api.deliveries.retry');
        Route::put('policy', [Portal\PolicyController::class, 'update'])->name('policy.update');
    });

    Route::middleware('can:manage-offices')->group(function () {
        Route::resource('offices', Portal\OfficeController::class)->except(['show', 'destroy']);
        Route::post('offices/{office}/gates', [Portal\OfficeController::class, 'storeGate'])->name('offices.gates.store');
        Route::patch('gates/{gate}', [Portal\OfficeController::class, 'toggleGate'])->name('gates.toggle');
        Route::post('gates/{gate}/reset-display', [Portal\OfficeController::class, 'resetDisplay'])->name('gates.reset-display');
    });

    Route::middleware('can:manage-employees')->group(function () {
        Route::get('employees/import', [Portal\EmployeeController::class, 'importForm'])->name('employees.import');
        Route::post('employees/import', [Portal\EmployeeController::class, 'import']);
        Route::get('employees/template.csv', [Portal\EmployeeController::class, 'template'])->name('employees.template');
        Route::post('employees/{employee}/activation', [Portal\EmployeeController::class, 'activation'])->middleware('throttle:20,1')->name('employees.activation');
        Route::resource('employees', Portal\EmployeeController::class)->except(['destroy']);
    });

    Route::middleware('can:approve-devices')->group(function () {
        Route::get('approvals', [Portal\ApprovalsController::class, 'index'])->name('approvals');
        Route::post('approvals/remote/{permission}', [Portal\ApprovalsController::class, 'remote'])->name('approvals.remote');
        Route::post('approvals/corrections/{correction}', [Portal\ApprovalsController::class, 'correction'])->name('approvals.correction');
        Route::get('remote', [Portal\RemoteController::class, 'index'])->name('remote.index');
        Route::post('remote', [Portal\RemoteController::class, 'store'])->name('remote.store');
        Route::post('remote/{permission}/revoke', [Portal\RemoteController::class, 'revoke'])->name('remote.revoke');
        Route::get('devices', [Portal\DeviceController::class, 'index'])->name('devices.index');
        Route::get('devices/{device}', [Portal\DeviceController::class, 'show'])->name('devices.show');
        Route::post('devices/{device}/{action}', [Portal\DeviceController::class, 'act'])
            ->whereIn('action', ['approve', 'reject', 'revoke'])->name('devices.act');
    });

    Route::middleware('can:manage-users')->group(function () {
        Route::get('users', [Portal\UserController::class, 'index'])->name('users.index');
        Route::post('users', [Portal\UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [Portal\UserController::class, 'update'])->name('users.update');
        Route::put('roles', [Portal\UserController::class, 'permissions'])->name('users.permissions');
    });
});
