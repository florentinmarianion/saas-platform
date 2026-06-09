<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Return JSON 401 instead of redirecting to 'login' route
        $middleware->redirectGuestsTo(fn() => response()->json([
            'message' => 'Unauthenticated.',
            'code'    => 'UNAUTHENTICATED',
        ], 401));

        // Force JSON + resolve tenant context on all API routes
        $middleware->appendToGroup('api', [
            \App\Http\Middleware\ForceJsonResponse::class,
            \App\Http\Middleware\ResolveTenantContext::class,
        ]);

        // Middleware aliases
        $middleware->alias([
            'tenant'  => \App\Http\Middleware\ResolveTenantContext::class,
            'company' => \App\Http\Middleware\EnsureCompanyContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (
            \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e,
            \Illuminate\Http\Request $request
        ): \Illuminate\Http\JsonResponse {
            return response()->json([
                'message' => 'This action is unauthorized.',
                'code'    => 'FORBIDDEN',
            ], 403);
        });

        $exceptions->render(function (
            \Illuminate\Database\Eloquent\ModelNotFoundException $e,
            \Illuminate\Http\Request $request
        ): \Illuminate\Http\JsonResponse {
            return response()->json([
                'message' => 'Resource not found.',
                'code'    => 'NOT_FOUND',
            ], 404);
        });

        $exceptions->render(function (
            \Illuminate\Validation\ValidationException $e,
            \Illuminate\Http\Request $request
        ): \Illuminate\Http\JsonResponse {
            return response()->json([
                'message' => 'Validation failed.',
                'code'    => 'VALIDATION_ERROR',
                'errors'  => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (
            \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e,
            \Illuminate\Http\Request $request
        ): \Illuminate\Http\JsonResponse {
            return response()->json([
                'message' => 'Method not allowed.',
                'code'    => 'METHOD_NOT_ALLOWED',
            ], 405);
        });

    })->create();
