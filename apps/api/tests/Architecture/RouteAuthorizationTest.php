<?php

namespace Tests\Architecture;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;

/**
 * Every signed-in API route says who may call it: a `can:` middleware, or a form request that
 * implements authorize(). A route that needs neither is listed here with the reason.
 */
class RouteAuthorizationTest extends TestCase
{
    /** uri => why it needs no permission of its own */
    private const OPEN = [
        'api/v1/auth/me' => 'your own session',
        'api/v1/auth/logout' => 'your own session',
        'api/v1/billing/status' => 'the subscription banner every screen shows',
        'api/v1/messages/templates' => 'the shop\'s message wording, used by every WhatsApp button',
    ];

    public function test_every_signed_in_route_checks_a_permission(): void
    {
        $unguarded = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            /** @var RoutingRoute $route */
            $middleware = $route->gatherMiddleware();
            if (! str_starts_with($route->uri(), 'api/v1/') || ! in_array('auth:sanctum', $middleware, true) || isset(self::OPEN[$route->uri()])) {
                continue;
            }
            if (array_filter($middleware, fn ($m) => is_string($m) && str_starts_with($m, 'can:')) !== []) {
                continue;
            }
            if ($this->authorizesInFormRequest($route) || $this->authorizesInController($route)) {
                continue;
            }
            $unguarded[] = implode('|', $route->methods()).' '.$route->uri();
        }

        $this->assertSame([], $unguarded, "Routes without a permission check:\n".implode("\n", $unguarded));
    }

    private function authorizesInFormRequest(RoutingRoute $route): bool
    {
        $method = $this->action($route);
        foreach ($method?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && is_subclass_of($type->getName(), FormRequest::class)
                && method_exists($type->getName(), 'authorize')
                && (new ReflectionMethod($type->getName(), 'authorize'))->getDeclaringClass()->getName() === $type->getName()) {
                return true;
            }
        }

        return false;
    }

    /** A controller action that checks for itself (abort_unless / Gate / can()). */
    private function authorizesInController(RoutingRoute $route): bool
    {
        $method = $this->action($route);
        if ($method === null || $method->getFileName() === false) {
            return false;
        }
        $lines = array_slice(file((string) $method->getFileName()) ?: [], $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1);

        return preg_match('/abort_unless\(.*->can\(|Gate::authorize|->authorize\(/', implode('', $lines)) === 1;
    }

    private function action(RoutingRoute $route): ?ReflectionMethod
    {
        $uses = $route->getAction('uses');
        if (! is_string($uses) || ! str_contains($uses, '@')) {
            return null;
        }
        [$class, $method] = explode('@', $uses);

        return method_exists($class, $method) ? new ReflectionMethod($class, $method) : null;
    }
}
