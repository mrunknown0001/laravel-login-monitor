<?php

declare(strict_types=1);

namespace Mrunknown0001\LaravelLoginMonitor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mrunknown0001\LaravelLoginMonitor\Contracts\ActivityLogger;
use Symfony\Component\HttpFoundation\Response;

final class LogRequestActivity
{
    public function __construct(private ActivityLogger $logger)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->headers->get('X-Request-ID') ?? $request->attributes->get('request_id');

        if (!$requestId) {
            $requestId = (string) Str::uuid();
            $request->attributes->set('request_id', $requestId);
        }

        $startedAt = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = (microtime(true) - $startedAt) * 1000;

        $routeName = $this->resolveRouteName($request);
        $controller = $this->resolveController($request);

        $this->logger->logRequest([
            'status' => $response->getStatusCode(),
            'duration_ms' => round($durationMs, 2),
            'request_id' => $requestId,
            'route' => $routeName,
            'controller' => $controller,
        ]);

        return $response;
    }

    private function resolveRouteName(Request $request): ?string
    {
        $route = $request->route();

        if (is_object($route) && method_exists($route, 'getName')) {
            return $route->getName() ?: null;
        }

        return is_string($route) ? $route : null;
    }

    private function resolveController(Request $request): ?string
    {
        $route = $request->route();

        if (!is_object($route) || !method_exists($route, 'getActionName')) {
            return null;
        }

        $action = $route->getActionName();

        return $action !== 'Closure' ? $action : null;
    }
}