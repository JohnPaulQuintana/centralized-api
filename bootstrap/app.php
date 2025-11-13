<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
// use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (Throwable $e, $request) {

            if ($request->is('api/*') || $request->expectsJson()) {

                // JWT exceptions
                if ($e instanceof TokenExpiredException) {
                    return response()->json(['error' => 'Token has expired'], 401);
                }

                if ($e instanceof TokenInvalidException) {
                    return response()->json(['error' => 'Token is invalid'], 401);
                }

                if ($e instanceof JWTException) {
                    return response()->json(['error' => 'Token is missing'], 401);
                }

                // Authentication
                if ($e instanceof AuthenticationException) {
                    return response()->json(['error' => 'Unauthenticated'], 401);
                }

                // HTTP exceptions (404, 403, etc.)
                if ($e instanceof HttpException) {
                    return response()->json(['error' => $e->getMessage() ?: 'HTTP Error'], $e->getStatusCode());
                }

                // Fallback server error
                return response()->json([
                    'error' => 'Server Error',
                    'message' => $e->getMessage(),
                ], 500);
            }

            // Default fallback for non-API requests
            return null;
        });
    })->create();
