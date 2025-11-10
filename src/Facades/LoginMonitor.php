<?php

namespace Mrunknown0001\LaravelLoginMonitor\Facades;

use Illuminate\Support\Facades\Facade;

class LoginMonitor extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'login-monitor';
    }
}