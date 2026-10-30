<?php

namespace App\Http\Middleware;

use App\Models\UserTest;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasVerifiedEmail()) {
            return redirect()->route('verify.email.notice')
                ->with('warning', 'Email của bạn chưa được xác minh. Vui lòng xác minh email để tiếp tục.');
        }

        if ($user->is_guest && ! $request->is('engine', 'engine/*', 'student/scores', 'student/scores/*')) {
            $trialAttempt = UserTest::ownedCompletedPublicTrial($user->id)
                ->latest('completed_at')
                ->first();

            if ($trialAttempt) {
                return redirect()->route('public-trial.result', $trialAttempt);
            }

            $examAttempt = UserTest::where('user_id', $user->id)
                ->whereNotNull('exam_session_id')
                ->where('status', 'completed')
                ->latest('completed_at')
                ->first();

            if ($examAttempt) {
                return redirect()->route('exam-join.result', $examAttempt);
            }

            return redirect()->route('public-trial.index');
        }

        return $next($request);
    }
}
