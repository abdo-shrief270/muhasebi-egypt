<?php

use App\Modules\Identity\Http\Middleware\ResolveBranch;
use App\Support\Modules\EnsureModuleEnabled;
use App\Support\Tenancy\ResolveTenant;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'module' => EnsureModuleEnabled::class,
            'branch' => ResolveBranch::class,
        ]);

        // Resolve the shop (and branch) before route model binding, so bound models are tenant scoped.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: ResolveBranch::class);
        $middleware->prependToPriorityList(before: ResolveBranch::class, prepend: ResolveTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Arabic messages + a stable `code` for the common HTTP errors.
        $json = fn (string $message, string $code, int $status) => response()->json(['message' => $message, 'code' => $code], $status);
        $exceptions->render(fn (AuthenticationException $e, Request $request) => $request->is('api/*') ? $json('لازم تسجّل دخول.', 'unauthenticated', 401) : null);
        $exceptions->render(fn (AccessDeniedHttpException $e, Request $request) => $request->is('api/*') ? $json('مش مسموح لك تعمل كده.', 'forbidden', 403) : null);
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->is('api/*') ? $json('مش موجود.', 'not_found', 404) : null);
        $exceptions->render(fn (TooManyRequestsHttpException $e, Request $request) => $request->is('api/*') ? $json('محاولات كتير، استنى شوية وجرّب تاني.', 'too_many_requests', 429) : null);
    })->create();
