<?php

namespace Mrunknown0001\LaravelLoginMonitor;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;

class LoginMonitorListener
{
    protected $monitor;

    public function __construct(LoginMonitor $monitor)
    {
        $this->monitor = $monitor;
    }

    public function handle($event)
    {
        if ($event instanceof Login) {
            $this->monitor->loginSuccess($event->user);
        } elseif ($event instanceof Failed) {
            $this->monitor->loginFailed($event->credentials);
        } elseif ($event instanceof Logout) {
            $this->monitor->logout($event->user);
        }
    }
}