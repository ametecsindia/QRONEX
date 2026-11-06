<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Mobile API auth: Bearer token issued at activation (SHA-256 stored) + device signature when the phone has a key. Revoked/rejected → 401. */
class DeviceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $device = $token ? Device::withoutGlobalScopes()->with('employee.office', 'tenant')->where('token_hash', hash('sha256', $token))->first() : null;

        if (! $device || in_array($device->status, ['revoked', 'rejected'], true) || $device->tenant?->isBlocked()) {
            return response()->json(['message' => 'This phone is not signed in.'], 401);
        }

        // Phones activated with a Keystore key must sign every request (ROVEXY scheme). Older installs without a key keep working.
        if ($device->public_key && ($err = \App\Support\DeviceSignature::check($request, $device->public_key))) {
            $msg = ['signature_stale' => "Your phone's date/time is wrong. Turn on automatic date & time and try again.",
                'signature_replayed' => 'That request was already received. Please try again.'][$err] ?? 'This request could not be verified as coming from your approved phone.';

            return response()->json(['message' => $msg, 'reason_code' => $err], $err === 'signature_stale' || $err === 'signature_replayed' ? 409 : 401);
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
