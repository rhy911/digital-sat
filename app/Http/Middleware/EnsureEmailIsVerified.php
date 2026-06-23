<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user ||
            ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail &&
                !$user->hasVerifiedEmail())) {
            
            return redirect()->route('verify.email.notice')
                ->with('warning', 'Email của bạn chưa được xác minh. Vui lòng xác minh email để tiếp tục.');
        }

        return $next($request);
    }
}
