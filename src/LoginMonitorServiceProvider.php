<?php

namespace Mrunknown0001\LaravelLoginMonitor;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Events\Created as ModelCreated;
use Illuminate\Database\Eloquent\Events\Updated as ModelUpdated;
use Illuminate\Database\Eloquent\Events\Deleted as ModelDeleted;
use Illuminate\Database\Events\QueryExecuted;

class LoginMonitorServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/login-monitor.php' => config_path('login-monitor.php'),
        ], 'login-monitor-config');

        // Register authentication event listeners
        $this->app['events']->listen(Login::class, LoginMonitorListener::class);
        $this->app['events']->listen(Failed::class, LoginMonitorListener::class);
        $this->app['events']->listen(Logout::class, LoginMonitorListener::class);

        // Register database mutation event listeners
        $this->app['events']->listen(ModelCreated::class, LoginMonitorListener::class);
        $this->app['events']->listen(ModelUpdated::class, LoginMonitorListener::class);
        $this->app['events']->listen(ModelDeleted::class, LoginMonitorListener::class);
        $this->app['events']->listen(QueryExecuted::class, LoginMonitorListener::class);
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/login-monitor.php', 'login-monitor'
        );

        $this->app->singleton('login-monitor', function ($app) {
            return new LoginMonitor(
                config('login-monitor.host'),
                config('login-monitor.enabled', true)
            );
        });

        // Register LoginMonitor class binding
        $this->app->singleton(LoginMonitor::class, function ($app) {
            return new LoginMonitor(
                config('login-monitor.host'),
                config('login-monitor.enabled', true)
            );
        });
    }
}