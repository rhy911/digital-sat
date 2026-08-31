<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RecycleBinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RecycleBinController extends Controller
{
    public function index(Request $request, RecycleBinService $recycleBin): JsonResponse
    {
        return response()->json(['data' => $recycleBin->itemsFor($request->user())]);
    }

    public function restore(Request $request, string $type, int $id, RecycleBinService $recycleBin): JsonResponse
    {
        try {
            $recycleBin->restoreItem($type, $id, $request->user());

            return response()->json(['status' => 'success', 'message' => 'Item restored successfully.']);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => collect($e->errors())->flatten()->first() ?: $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function destroy(Request $request, string $type, int $id, RecycleBinService $recycleBin): JsonResponse
    {
        try {
            $result = $recycleBin->permanentlyDeleteItem($type, $id, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => collect($e->errors())->flatten()->first() ?: $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (RecycleBinBlockedException $e) {
            return response()->json([
                'status' => 'blocked',
                'message' => $e->getMessage(),
                'data' => ['references' => $e->references],
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to permanently delete recycle-bin item.', [
                'type' => $type,
                'id' => $id,
                'exception' => $e,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Could not permanently delete this item. Please try again.',
            ], 500);
        }
    }

    public function destroyAll(Request $request, RecycleBinService $recycleBin): JsonResponse
    {
        try {
            $result = $recycleBin->permanentlyDeleteAll($request->user());

            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to empty recycle bin.', ['exception' => $e]);

            return response()->json([
                'status' => 'error',
                'message' => 'Could not empty the recycle bin. Please try again.',
            ], 500);
        }
    }
}
