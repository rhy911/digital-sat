<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Http\Requests\Engine\SubmitModuleRequest;
use App\Models\UserTest;
use App\Services\ExamAttemptEngine;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function __construct(private ExamAttemptEngine $engine) {}

    public function submit(SubmitModuleRequest $request)
    {
        $result = $this->engine->submit($request->validated());

        $response = response()->json($result['data'], $result['status_code'] ?? 200);
        if (! empty($result['headers'])) {
            foreach ($result['headers'] as $header => $val) {
                $response->header($header, $val);
            }
        }

        return $response;
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
