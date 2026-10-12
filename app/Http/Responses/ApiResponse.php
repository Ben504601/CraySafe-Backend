<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Standardized API response builder.
 *
 * Every controller method in this project should return one of
 * these helpers so that the JSON shape is identical everywhere.
 *
 * Success: { success: true, message: string, data: mixed|null }
 * Error:   { success: false, message: string, code: string, errors: object|null }
 */
trait ApiResponse
{
    /**
     * Return a successful response.
     *
     * @param  mixed   $data     Payload (array, model, collection, null)
     * @param  string  $message  Human-readable status message
     * @param  int     $status   HTTP status (default 200)
     */
    protected function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Return an error response.
     *
     * @param  string      $message  Human-readable error
     * @param  string      $code     Machine-readable error code (e.g., "UNAUTHORIZED")
     * @param  int         $status   HTTP status (default 400)
     * @param  array|null  $errors   Field-level errors for validation failures
     */
    protected function error(
        string $message,
        string $code = 'ERROR',
        int $status = 400,
        ?array $errors = null
    ): JsonResponse {
        $body = [
            'success' => false,
            'message' => $message,
            'code'    => $code,
        ];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status);
    }

    // ─────────────────────────────────────────────────────────
    // Semantic shortcuts — same shape, less typing in controllers
    // ─────────────────────────────────────────────────────────

    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, 'UNAUTHORIZED', 401);
    }

    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, 'FORBIDDEN', 403);
    }

    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, 'NOT_FOUND', 404);
    }

    protected function validationFailed(
        array $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        return $this->error($message, 'VALIDATION_FAILED', 422, $errors);
    }

    protected function serverError(string $message = 'Server error'): JsonResponse
    {
        return $this->error($message, 'SERVER_ERROR', 500);
    }
}