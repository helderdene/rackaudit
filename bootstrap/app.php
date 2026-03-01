<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Define the auth middleware group with Laravel's Authenticate middleware
        // plus our custom EnsureUserIsActive check
        $middleware->group('auth', [
            \Illuminate\Auth\Middleware\Authenticate::class,
            EnsureUserIsActive::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'ensure.active' => EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle Spatie UnauthorizedException (thrown by permission middleware)
        $exceptions->render(function (UnauthorizedException $e, Request $request): Response {
            $user = $request->user();

            // Log the unauthorized access attempt
            Log::warning('Unauthorized access attempt', [
                'user_id' => $user?->id,
                'route' => $request->path(),
                'timestamp' => now()->toISOString(),
                'required_permissions' => $e->getRequiredPermissions(),
                'required_roles' => $e->getRequiredRoles(),
            ]);

            // Store flash message in session
            session()->flash('error', 'You do not have permission to access this resource.');

            // Handle Inertia requests by redirecting back with error
            if ($request->header('X-Inertia')) {
                return Inertia::location(url()->previous());
            }

            // Return 403 forbidden response
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You do not have permission to access this resource.',
                ], 403);
            }

            return response()->view('errors.403', [
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        });

        // Handle standard Laravel AuthorizationException (thrown by gates/policies)
        $exceptions->render(function (AuthorizationException $e, Request $request): Response {
            $user = $request->user();

            // Log the unauthorized access attempt
            Log::warning('Unauthorized access attempt', [
                'user_id' => $user?->id,
                'route' => $request->path(),
                'timestamp' => now()->toISOString(),
            ]);

            // Store flash message in session
            session()->flash('error', 'You do not have permission to access this resource.');

            // Handle Inertia requests by redirecting back with error
            if ($request->header('X-Inertia')) {
                return Inertia::location(url()->previous());
            }

            // Return 403 forbidden response
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You do not have permission to access this resource.',
                ], 403);
            }

            return response()->view('errors.403', [
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        });

        // Handle AuthenticationException (thrown when user is not logged in)
        $exceptions->render(function (AuthenticationException $e, Request $request): ?Response {
            // Handle Inertia requests by redirecting to login
            if ($request->header('X-Inertia')) {
                return Inertia::location(route('login'));
            }

            // Let Laravel handle non-Inertia requests (default redirect to login)
            return null;
        });
    })->create();
