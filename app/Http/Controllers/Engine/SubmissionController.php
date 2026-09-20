<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Engine\Concerns\HandlesEngineErrors;
use App\Http\Requests\Engine\SubmitModuleRequest;
use App\Models\UserTest;
use App\Services\AttemptEngine;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    use HandlesEngineErrors;

    public function __construct(private AttemptEngine $engine) {}

    public function submit(SubmitModuleRequest $request)
    {
        return $this->handleEngineErrors(function () use ($request) {
            $result = $this->engine->submit($request->validated(), $request->user()?->id);

            if (($result['status'] ?? null) === 'in_flight') {
                return response()->json([
                    'error' => 'submission_in_progress',
                    'message' => 'Submission is processing',
                ], 409)->header('Retry-After', 2);
            }

            if (in_array($result['status'] ?? null, ['ready', 'completed'], true)) {
                $data = $result['result'];

                return response()->json($data, isset($data['error']) ? 500 : 200);
            }

            return response()->json([
                'status' => 'scoring',
                'message' => 'Scoring in progress...',
            ]);
        }, 'Unauthorized submission.');
    }

    public function checkStatus(Request $request, UserTest $userTest)
    {
        $this->authorize('view', $userTest);

        $ready = $this->engine->readyResultFor($userTest->id, (int) $request->query('module_id', 0));

        if ($ready !== null) {
            return response()->json($ready);
        }

        return response()->json([
            'status' => 'scoring',
            'message' => 'Scoring in progress...',
        ]);
    }
}
