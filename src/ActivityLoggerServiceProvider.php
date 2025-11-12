<?php

declare(strict_types=1);

namespace Mrunknown0001\LaravelLoginMonitor;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Mrunknown0001\LaravelLoginMonitor\Contracts\ActivityLogger as ActivityLoggerContract;
use Mrunknown0001\LaravelLoginMonitor\ActivityLogger as ActivityLoggerService;
use Mrunknown0001\LaravelLoginMonitor\Http\Middleware\LogRequestActivity;
use Mrunknown0001\LaravelLoginMonitor\Listeners\LogAuthenticationEvents;

final class ActivityLoggerServiceProvider extends ServiceProvider
{
    public function boot(Dispatcher $events): void
    {
        $this->publishes([
            __DIR__ . '/../config/activity-logger.php' => config_path('activity-logger.php'),
        ], 'activity-logger-config');

        $this->loadRoutes();
        $this->registerMiddleware();
        $this->registerAuthenticationListeners($events);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/activity-logger.php',
            'activity-logger'
        );

        $this->registerBindings();
    }

    /**
     * @throws BindingResolutionException
     */
    protected function registerBindings(): void
    {
        $this->app->singleton(ActivityLoggerContract::class, static function (Application $app) {
            return new ActivityLoggerService(
                config('activity-logger', [])
            );
        });

        $this->app->alias(ActivityLoggerContract::class, ActivityLoggerService::class);
        $this->app->alias(ActivityLoggerContract::class, 'activity-logger');
    }

    protected function registerMiddleware(): void
    {
        if (!config('activity-logger.features.autoload_middleware', false)) {
            return;
        }

        $kernel = $this->app->make(Kernel::class);

        $kernel->pushMiddleware(LogRequestActivity::class);
    }

    protected function registerAuthenticationListeners(Dispatcher $events): void
    {
        if (!config('activity-logger.features.log_authentication_events', true)) {
            return;
        }

        $listener = LogAuthenticationEvents::class;

        $events->listen(Login::class, $listener);
        $events->listen(Failed::class, $listener);
        $events->listen(Logout::class, $listener);
    }

    protected function loadRoutes(): void
    {
        if (!$this->app->bound(Router::class)) {
            return;
        }

        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('activity.log', LogRequestActivity::class);
    }
}