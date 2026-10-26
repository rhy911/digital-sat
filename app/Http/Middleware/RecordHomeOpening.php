<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\UserTest;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordHomeOpening
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();
        if ($response->getStatusCode() !== 200 || $user?->role !== 'student') {
            return $response;
        }

        try {
            $field = null;
            if ($request->routeIs('engine.session') && in_array($request->route('ulid'), [null, 'preview-rw', 'preview-math'], true)) {
                $field = 'home_preview_opened_at';
            } elseif ($request->routeIs('student.scores.show')) {
                $attempt = $request->route('userTest');
                if ($attempt instanceof UserTest && (int) $attempt->user_id === (int) $user->id &&
                    $attempt->status === 'completed' && UserTest::whereKey($attempt->id)
                        ->whereHas('test', fn ($query) => $query->where('title', '!=', 'Test Preview'))
                        ->excludingAbsorbedSections()->exists()) {
                    $field = 'home_report_opened_at';
                }
            }
            if ($field && $user->getAttribute($field) === null) {
                User::whereKey($user->id)->whereNull($field)->update([$field => now()]);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $response;
    }
}
