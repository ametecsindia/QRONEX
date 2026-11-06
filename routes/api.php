<?php

use App\Http\Controllers\Api\MobileController;
use App\Http\Controllers\Api\PublicApiController as P;
use App\Http\Controllers\Api\RazorpayWebhookController;
use App\Http\Middleware\ApiKeyAuth;
use App\Http\Middleware\DeviceToken;
use Illuminate\Support\Facades\Route;

// Employee app — see mobile/README.md
Route::prefix('v1')->group(function () {
    Route::post('activate', [MobileController::class, 'activate'])->middleware('throttle:10,1');

    Route::middleware(DeviceToken::class)->group(function () {
        Route::get('me', [MobileController::class, 'me'])->middleware('throttle:60,1');
        Route::post('scan', [MobileController::class, 'scan'])->middleware('throttle:20,1');
        Route::get('history', [MobileController::class, 'history'])->middleware('throttle:30,1');
        Route::post('remote-scan', [MobileController::class, 'remoteScan'])->middleware('throttle:20,1');
        Route::post('remote-requests', [MobileController::class, 'requestRemote'])->middleware('throttle:10,1');
        Route::post('corrections', [MobileController::class, 'requestCorrection'])->middleware('throttle:10,1');
        Route::get('requests', [MobileController::class, 'requests'])->middleware('throttle:30,1');
    });
});

// Public REST API for integrations (HRMS, payroll, SmartPRS…) — company API keys. Docs: /docs/api
Route::prefix('v1')->group(function () {
    $k = fn (string $scope) => ApiKeyAuth::class.':'.$scope;
    Route::get('offices', [P::class, 'offices'])->middleware($k('offices:read'));
    Route::get('employees', [P::class, 'employees'])->middleware($k('employees:read'));
    Route::get('employees/{code}', [P::class, 'showEmployee'])->middleware($k('employees:read'));
    Route::post('employees', [P::class, 'createEmployee'])->middleware($k('employees:write'));
    Route::patch('employees/{code}', [P::class, 'updateEmployee'])->middleware($k('employees:write'));
    Route::post('employees/{code}/deactivate', [P::class, 'deactivateEmployee'])->middleware($k('employees:write'));
    Route::get('attendance/events', [P::class, 'events'])->middleware($k('attendance:read'));
    Route::get('attendance/events/{ref}', [P::class, 'showEvent'])->middleware($k('attendance:read'));
    Route::get('attendance/export', [P::class, 'export'])->middleware($k('attendance:export'));
    Route::get('remote-permissions', [P::class, 'remotePermissions'])->middleware($k('remote:write'));
    Route::post('remote-permissions', [P::class, 'grantRemote'])->middleware($k('remote:write'));
    Route::get('webhooks', [P::class, 'webhooks'])->middleware($k('webhooks:manage'));
    Route::post('webhooks', [P::class, 'createWebhook'])->middleware($k('webhooks:manage'));
    Route::delete('webhooks/{id}', [P::class, 'deleteWebhook'])->whereNumber('id')->middleware($k('webhooks:manage'));
    Route::get('openapi.json', \App\Http\Controllers\Api\OpenApiController::class)->name('openapi');
});

Route::post('razorpay/webhook', RazorpayWebhookController::class)->middleware('throttle:120,1');
