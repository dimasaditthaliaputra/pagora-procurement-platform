<?php

declare(strict_types=1);

use App\Helpers\ResponseHelper;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureSuperadmin;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'superadmin' => EnsureSuperadmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(ResponseHelper::SENSITIVE_INPUT);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if ($request->is('api/*') || $request->expectsJson()) {
                return $response;
            }

            // Expired CSRF token / session: send the user back with a message instead of an error modal.
            if ($status === 419) {
                return back()->with('error', 'Sesi halaman telah berakhir. Silakan coba lagi.');
            }

            // Keep Laravel's debug page for server errors during development.
            $showErrorPage = in_array($status, [403, 404, 429, 503], true)
                || ($status === 500 && ! config('app.debug'));

            if (! $showErrorPage) {
                return $response;
            }

            return Inertia::render('Error', [
                'status' => $status,
                'request_id' => $status === 500 ? Context::get('request_id') : null,
            ])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
