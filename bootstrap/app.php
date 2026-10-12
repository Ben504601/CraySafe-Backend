<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Render terminates TLS at its edge and forwards via X-Forwarded-For.
        // Without this, $request->ip() returns Render's LB IP and every user
        // shares one rate-limit bucket.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // ─── Validation Errors (422) ──────────────────────────────
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->validator->errors()->first(),
                    'code' => 'VALIDATION_FAILED',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // ─── Unauthenticated (401) ────────────────────────────────
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required.',
                    'code' => 'UNAUTHORIZED',
                ], 401);
            }
        });

        // ─── Rate Limited (429) ───────────────────────────────────
        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please slow down.',
                    'code' => 'RATE_LIMITED',
                    'retry_after' => $e->getHeaders()['Retry-After'] ?? null,
                ], 429);
            }
        });

        // ─── Model Not Found (404) ────────────────────────────────
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'The requested resource was not found.',
                    'code' => 'NOT_FOUND',
                ], 404);
            }
        });

        // ─── Route Not Found (404) ────────────────────────────────
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint not found.',
                    'code' => 'NOT_FOUND',
                ], 404);
            }
        });

        // ─── Method Not Allowed (405) ─────────────────────────────
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'HTTP method not allowed for this endpoint.',
                    'code' => 'METHOD_NOT_ALLOWED',
                ], 405);
            }
        });

        // ─── Generic HTTP Exceptions (any status) ─────────────────
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'HTTP error',
                    'code' => 'HTTP_' . $e->getStatusCode(),
                ], $e->getStatusCode());
            }
        });

        // ─── Database Errors (500) ────────────────────────────────
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->is('api/*')) {
                Log::error('Database error', [
                    'sql' => $e->getSql(),
                    'bindings' => $e->getBindings(),
                    'message' => $e->getMessage(),
                ]);

                // Never leak SQL to the client
                return response()->json([
                    'success' => false,
                    'message' => 'A server error occurred. Please try again.',
                    'code' => 'SERVER_ERROR',
                ], 500);
            }
        });

        // ─── Catch-All for Anything Else (500) ────────────────────
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                Log::error('Unhandled exception', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                // In debug mode, show the real error for development
                $message = config('app.debug')
                    ? $e->getMessage()
                    : 'A server error occurred. Please try again.';

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'code' => 'SERVER_ERROR',
                ], 500);
            }
        });

    })->create();