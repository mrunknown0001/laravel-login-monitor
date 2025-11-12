<?php

declare(strict_types=1);

namespace Mrunknown0001\LaravelLoginMonitor\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Mrunknown0001\LaravelLoginMonitor\Contracts\ActivityLogger;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(static function (Model $model): void {
            $model->recordModelActivity('model_created', [
                'attributes' => $model->getAttributes(),
            ]);
        });

        static::updated(static function (Model $model): void {
            $model->recordModelActivity('model_updated', [
                'original' => $model->getOriginal(),
                'changes' => $model->getChanges(),
            ]);
        });

        static::deleted(static function (Model $model): void {
            $model->recordModelActivity('model_deleted', [
                'original' => $model->getOriginal(),
            ]);
        });

        static::restored(static function (Model $model): void {
            $model->recordModelActivity('model_restored', [
                'attributes' => $model->getAttributes(),
            ]);
        });

        static::forceDeleted(static function (Model $model): void {
            $model->recordModelActivity('model_force_deleted', [
                'original' => $model->getOriginal(),
            ]);
        });
    }

    protected function recordModelActivity(string $event, array $meta = []): void
    {
        if (!$this->shouldLogActivityEvent($event)) {
            return;
        }

        /** @var \Mrunknown0001\LaravelLoginMonitor\Contracts\ActivityLogger $logger */
        $logger = App::make(ActivityLogger::class);

        $logger->logModelEvent(
            $event,
            array_merge(
                $this->defaultActivityMeta(),
                $meta,
                $this->activityLoggerExtraMeta($event)
            )
        );
    }

    protected function shouldLogActivityEvent(string $event): bool
    {
        return true;
    }

    protected function activityLoggerExtraMeta(string $event): array
    {
        return [];
    }

    protected function defaultActivityMeta(): array
    {
        return [
            'model' => static::class,
            'id' => $this->getKey(),
            'table' => $this->getTable(),
        ];
    }
}