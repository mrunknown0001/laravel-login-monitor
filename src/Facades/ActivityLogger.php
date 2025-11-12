<?php

declare(strict_types=1);

namespace Mrunknown0001\LaravelLoginMonitor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void log(string $event, array $payload = [])
 * @method static void logModelEvent(string $event, array $meta = [])
 * @method static void logRequest(array $meta = [])
 */
final class ActivityLogger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'activity-logger';
    }
}