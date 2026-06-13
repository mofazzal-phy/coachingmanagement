<?php

namespace Modules\Core\app\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BaseApiController extends Controller
{
    /**
     * Success response.
     */
    protected function success($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Error response.
     */
    protected function error(string $message = 'Error', int $code = 400, $errors = null): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
            'data' => null,
        ], $code);
    }

    /**
     * Paginated response with meta data.
     */
    protected function paginatedResponse(LengthAwarePaginator $paginator, string $message = 'Success'): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'next_page_url' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Collection response.
     *
     * @param \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection $collection
     */
    protected function collectionResponse($collection, string $message = 'Success'): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $collection->values()->all(),
        ]);
    }

    /**
     * Created response.
     */
    protected function created($data = null, string $message = 'Created successfully'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    /**
     * No content response.
     */
    protected function noContent(string $message = 'Deleted successfully'): JsonResponse
    {
        return $this->success(null, $message, 200);
    }

    /**
     * Validation error response.
     */
    protected function validationError($errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->error($message, 422, $errors);
    }

    /**
     * Not found response.
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, 404);
    }

    /**
     * Forbidden response.
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, 403);
    }

    /**
     * Unauthorized response.
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, 401);
    }

    /**
     * Get per page from request.
     */
    protected function getPerPage(Request $request, int $default = 15): int
    {
        return min((int) $request->input('per_page', $default), 100);
    }

    /**
     * Get sort parameters from request.
     */
    protected function getSortParams(Request $request, string $defaultField = 'created_at', string $defaultOrder = 'desc'): array
    {
        $allowedFields = ['created_at', 'updated_at', 'name', 'id'];

        $field = $request->input('sort_by', $defaultField);
        if (!in_array($field, $allowedFields)) {
            $field = $defaultField;
        }

        $order = $request->input('sort_order', $defaultOrder);
        if (!in_array(strtolower($order), ['asc', 'desc'])) {
            $order = $defaultOrder;
        }

        return [$field, $order];
    }
}
