<?php

namespace Mrunknown0001\LaravelLoginMonitor;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;

class LoginMonitorServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/login-monitor.php' => config_path('login-monitor.php'),
        ], 'login-monitor-config');

        // Register event listeners
        $this->app['events']->listen(Login::class, LoginMonitorListener::class);
        $this->app['events']->listen(Failed::class, LoginMonitorListener::class);
        $this->app['events']->listen(Logout::class, LoginMonitorListener::class);
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