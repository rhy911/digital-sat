<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\UserTest;
use App\Services\AttemptEngine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AnswerController extends Controller
{
    public function __construct(private AttemptEngine $engine) {}

    public function timing(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_test_id' => 'required|integer',
            'module_id' => 'required|exists:modules,id',
        ]);

        $attempt = UserTest::query()
            ->whereKey($validated['user_test_id'])
            ->where('user_id', $request->user()?->id)
            ->first();
        if (! $attempt) {
            return response()->json(['error' => 'unauthorized_attempt'], 403);
        }

        $module = Module::query()
            ->whereKey($validated['module_id'])
            ->whereHas('sections', fn ($sections) => $sections->where('test_id', $attempt->test_id))
            ->first();
        if (! $module) {
            return response()->json(['error' => 'module_outside_attempt'], 403);
        }

        if ($attempt->status === 'completed') {
            $resultUrl = $attempt->is_public_trial && $attempt->user?->is_guest
                ? route('public-trial.result', $attempt)
                : ($attempt->user?->is_guest
                ? route('exam-join.result', $attempt)
                : route('student.scores.show', $attempt));

            return response()->json(['status' => 'completed', 'redirect_url' => $resultUrl])
                ->header('Cache-Control', 'no-store, private');
        }

        $wasIssued = (int) $attempt->current_module_id === (int) $module->id
            || $attempt->moduleSubmissions()
                ->where(fn ($submissions) => $submissions
                    ->where('module_id', $module->id)
                    ->orWhere('issued_next_module_id', $module->id))
                ->exists();
        if (! $wasIssued) {
            return response()->json(['error' => 'module_not_issued'], 403);
        }

        if ($attempt->status !== 'in_progress' || ! $attempt->current_module_id) {
            return response()->json(['error' => 'attempt_unavailable'], 409)
                ->header('Cache-Control', 'no-store, private');
        }

        if ((int) $attempt->current_module_id !== (int) $module->id) {
            $currentModule = Module::query()
                ->whereKey($attempt->current_module_id)
                ->whereHas('sections', fn ($sections) => $sections->where('test_id', $attempt->test_id))
                ->first();
            if (! $currentModule) {
                return response()->json(['error' => 'attempt_unavailable'], 409)
                    ->header('Cache-Control', 'no-store, private');
            }

            return response()->json([
                'status' => 'advanced',
                'navigation_url' => route('engine.session', ['ulid' => $currentModule->ulid, 'attempt' => $attempt->ulid]),
            ])->header('Cache-Control', 'no-store, private');
        }

        $pendingBreak = $this->engine->pendingBreak($attempt, $module);
        if ($pendingBreak !== null) {
            return response()->json([
                'status' => 'break',
                'module_id' => (int) $module->id,
                'remaining_break_seconds' => $pendingBreak['remaining_seconds'],
                'break_ends_at' => $pendingBreak['break_ends_at'],
                'duration_seconds' => $pendingBreak['duration_seconds'],
            ])->header('Cache-Control', 'no-store, private');
        }

        if (! $attempt->runsOnChainedClock() && $attempt->current_module_paused) {
            return response()->json($this->engine->timingSnapshot($attempt, $module) + ['status' => 'paused'])
                ->header('Cache-Control', 'no-store, private');
        }

        return response()->json($this->engine->timingSnapshot($attempt, $module) + ['status' => 'active'])
            ->header('Cache-Control', 'no-store, private');
    }

    public function control(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_test_id' => 'required|integer',
            'module_id' => 'required|integer',
            'action' => 'required|in:claim,heartbeat,pause,resume,takeover,release',
        ]);

        try {
            $result = $this->engine->controlAttempt(
                (int) $validated['user_test_id'],
                (int) $validated['module_id'],
                (int) $request->user()->id,
                $validated['action'],
                $request->header('X-Attempt-Control'),
            );

            return response()->json($result)->header('Cache-Control', 'no-store, private');
        } catch (AuthorizationException $e) {
            return response()->json(['error' => 'unauthorized_attempt', 'message' => $e->getMessage()], 403);
        } catch (ConflictHttpException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }
    }

    public function resumeBreak(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_test_id' => 'required|integer|exists:user_tests,id',
            'module_id' => 'required',
        ]);

        $rawModuleId = $validated['module_id'];
        $module = is_numeric($rawModuleId)
            ? Module::find($rawModuleId)
            : Module::where('ulid', $rawModuleId)->first();

        if (! $module) {
            return response()->json(['error' => 'module_not_found'], 404);
        }

        $attempt = UserTest::query()
            ->whereKey($validated['user_test_id'])
            ->where('user_id', $request->user()?->id)
            ->first();
        if (! $attempt) {
            return response()->json(['error' => 'unauthorized_attempt'], 403);
        }

        try {
            $controllerToken = $request->header('X-Attempt-Control');
            $this->engine->assertAttemptController($attempt, $controllerToken);
            $timing = $this->engine->resumeBreak(
                (int) $validated['user_test_id'],
                (int) $module->id,
                $request->user()?->id,
                $controllerToken,
            );

            return response()->json($timing)->header('Cache-Control', 'no-store, private');
        } catch (AuthorizationException $e) {
            return response()->json(['error' => 'unauthorized_attempt', 'message' => $e->getMessage()], 403);
        } catch (ConflictHttpException $e) {
            return response()->json(['error' => 'module_conflict', 'message' => $e->getMessage()], 409);
        }
    }

    public function autosave(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_test_id' => 'required|integer',
            'module_id' => 'required|exists:modules,id',
            'answers' => 'present|array|max:100',
            'answers.*' => 'nullable|string|max:100',
            'question_times' => 'nullable|array|max:100',
            'question_times.*' => 'nullable|integer|min:0',
        ]);

        try {
            [$attempt, $module] = $this->engine->resolveActiveModule(
                (int) $validated['user_test_id'],
                (int) $validated['module_id'],
                $request->user()?->id,
            );
            $controllerToken = $request->header('X-Attempt-Control');
            $this->engine->assertAttemptController($attempt, $controllerToken);

            $result = $this->engine->autosave(
                $attempt,
                $module,
                $validated['answers'],
                $validated['question_times'] ?? [],
                $controllerToken,
            );

            if ($result['expired'] ?? false) {
                return response()->json([
                    'error' => 'module_expired',
                    'message' => 'Module time has expired. Saved answers will be submitted.',
                ], 409);
            }

            if (($result['status'] ?? null) === 'submitting') {
                return response()->json([
                    'error' => 'submission_in_progress',
                    'message' => 'Submission is processing',
                ], 409);
            }

            return response()->json([
                'status' => 'success',
                'saved_count' => $result['saved_count'],
                'message' => 'Answers autosaved.',
            ]);
        } catch (AuthorizationException $e) {
            return response()->json([
                'error' => 'Unauthorized autosave.',
                'message' => $e->getMessage(),
            ], 403);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'error' => in_array($e->getMessage(), ['controller_lost', 'practice_paused'], true)
                    ? $e->getMessage()
                    : 'module_progression_conflict',
                'message' => $e->getMessage(),
            ], 409);
        }
    }
}
