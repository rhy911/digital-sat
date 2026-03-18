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
        
        // Log để debug
        \Illuminate\Support\Facades\Log::info('Middleware verified check', [
            'user_id' => $user?->id,
            'email' => $user?->email,
            'email_verified_at' => $user?->email_verified_at,
            'has_verified_email' => $user?->hasVerifiedEmail(),
            'path' => $request->path(),
        ]);

        if (!$user ||
            ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail &&
                !$user->hasVerifiedEmail())) {
            
            return redirect()->route('verify.email.notice')
                ->with('warning', 'Email của bạn chưa được xác minh. Vui lòng xác minh email để tiếp tục.');
        }

        return $next($request);
    }
}
