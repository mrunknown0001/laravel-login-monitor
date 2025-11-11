<?php

namespace Mrunknown0001\LaravelLoginMonitor;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Events\Created as ModelCreated;
use Illuminate\Database\Eloquent\Events\Deleted as ModelDeleted;
use Illuminate\Database\Eloquent\Events\Updated as ModelUpdated;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Str;

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

            return;
        }

        if ($event instanceof Failed) {
            $this->monitor->loginFailed($event->credentials);

            return;
        }

        if ($event instanceof Logout) {
            $this->monitor->logout($event->user);

            return;
        }

        if ($event instanceof ModelCreated) {
            $this->monitor->recordCreated($event->model);

            return;
        }

        if ($event instanceof ModelUpdated) {
            $this->monitor->recordUpdated($event->model);

            return;
        }

        if ($event instanceof ModelDeleted) {
            $this->monitor->recordDeleted($event->model);

            return;
        }

        if ($event instanceof QueryExecuted) {
            $this->handleQueryExecuted($event);
        }
    }

    protected function handleQueryExecuted(QueryExecuted $event): void
    {
        $operation = $this->extractOperation($event->sql);

        if (!$operation) {
            return;
        }

        if ($this->isEloquentQuery()) {
            return;
        }

        $table = $this->extractTableName($event->sql, $operation);

        $meta = array_filter([
            'source' => 'query_builder',
            'connection' => $event->connectionName,
            'table' => $table,
            'sql' => $event->sql,
            'bindings' => $event->bindings,
            'execution_time_ms' => $event->time,
        ], static fn ($value) => $value !== null && $value !== '');

        $this->monitor->recordQueryBuilderOperation($operation, $meta);
    }

    protected function extractOperation(string $sql): ?string
    {
        $sql = ltrim(strtolower($sql));

        if (Str::startsWith($sql, 'insert')) {
            return 'create';
        }

        if (Str::startsWith($sql, 'update')) {
            return 'update';
        }

        if (Str::startsWith($sql, 'delete')) {
            return 'delete';
        }

        return null;
    }

    protected function extractTableName(string $sql, string $operation): ?string
    {
        $patterns = [
            'create' => '/insert\s+into\s+[`"\[]?([a-z0-9_.]+)[`"\]]?/i',
            'update' => '/update\s+[`"\[]?([a-z0-9_.]+)[`"\]]?/i',
            'delete' => '/delete\s+from\s+[`"\[]?([a-z0-9_.]+)[`"\]]?/i',
        ];

        $pattern = $patterns[$operation] ?? null;

        if (!$pattern) {
            return null;
        }

        if (preg_match($pattern, $sql, $matches)) {
            return trim($matches[1], '`"[]');
        }

        return null;
    }

    protected function isEloquentQuery(): bool
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20) as $frame) {
            if (!isset($frame['class'])) {
                continue;
            }

            if (Str::startsWith($frame['class'], 'Illuminate\\Database\\Eloquent\\')) {
                return true;
            }
        }

        return false;
    }
}