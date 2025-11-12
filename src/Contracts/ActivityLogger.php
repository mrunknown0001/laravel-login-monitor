<?php

declare(strict_types=1);

namespace Mrunknown0001\LaravelLoginMonitor\Contracts;

use Illuminate\Contracts\Support\Arrayable;

interface ActivityLogger
{
    /**
     * Log a generic event payload.
     *
     * @param  string  $event
     * @param  array|Arrayable<string,mixed>  $payload
     */
    public function log(string $event, array|Arrayable $payload = []): void;

    /**
     * Log model lifecycle activity.
     *
     * @param  string  $event
     * @param  array<string,mixed>  $meta
     */
    public function logModelEvent(string $event, array $meta = []): void;

    /**
     * Log an inbound HTTP request lifecycle event.
     *
     * @param  array<string,mixed>  $meta
     */
    public function logRequest(array $meta = []): void;
}