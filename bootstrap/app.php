<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->api(prepend: [
            \App\Http\Middleware\ApiRequestContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Unauthenticated.',
                'request_id' => $request->attributes->get('request_id'),
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Forbidden.',
                'request_id' => $request->attributes->get('request_id'),
            ], 403);
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Forbidden.',
                'request_id' => $request->attributes->get('request_id'),
            ], 403);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
                'request_id' => $request->attributes->get('request_id'),
            ], 422);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Resource not found.',
                'request_id' => $request->attributes->get('request_id'),
            ], 404);
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $requestId = $request->attributes->get('request_id');
            $userId = $request->user()?->id;

            if ($status >= 500) {
                Log::error('API request failed', [
                    'request_id' => $requestId,
                    'user_id' => $userId,
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'status' => $status,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            } else {
                Log::warning('API request rejected', [
                    'request_id' => $requestId,
                    'user_id' => $userId,
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'status' => $status,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'message' => $status >= 500 ? 'Server error.' : ($e->getMessage() ?: 'Request failed.'),
                'request_id' => $requestId,
            ], $status);
        });
    })->create();
