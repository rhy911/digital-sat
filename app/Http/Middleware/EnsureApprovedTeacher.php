<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedTeacher
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'admin' && !$request->user()?->isApprovedTeacher()) {
            return redirect()->route('teacher.application.status');
        }

        return $next($request);
    }
}
