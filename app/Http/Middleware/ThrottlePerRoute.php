<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Laravel keys throttle:x,y by domain + IP (or user) only, so every throttled route
 * shares one counter. A gate display polling its token from the office IP then used up
 * throttle:5,1 on /register before anyone submitted. Each route now counts separately.
 */
class ThrottlePerRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        return sha1(parent::resolveRequestSignature($request).'|'.$request->method().'|'.$request->route()->uri());
    }
}
