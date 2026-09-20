<?php

namespace App\Http\Controllers\Engine\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

trait HandlesEngineErrors
{
    protected function handleEngineErrors(callable $callback, string $unauthorizedMessage = 'Unauthorized.')
    {
        try {
            return $callback();
        } catch (AuthorizationException $e) {
            return response()->json([
                'error' => $unauthorizedMessage,
                'message' => $e->getMessage(),
            ], 403);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'error' => 'module_progression_conflict',
                'message' => $e->getMessage(),
            ], 409);
        }
    }
}
