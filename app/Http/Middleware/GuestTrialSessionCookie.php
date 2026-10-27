<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GuestTrialSessionCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $sessionName = (string) config('session.cookie');
        $guestTrial = ($request->user()?->is_guest && $request->session()->has('public_trial_session'))
            || (! $request->user() && $request->session()->has('link_guest_user_id')
                && ($request->session()->has('public_trial_session') || $request->session()->has('link_trial_attempt_ulid')));

        if (! $guestTrial) {
            return $response;
        }

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $sessionName) {
                $response->headers->setCookie($cookie->withExpires(0));
                break;
            }
        }

        return $response;
    }
}
