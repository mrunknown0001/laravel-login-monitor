<?php

declare(strict_types=1);

namespace Mrunknown0001\LaravelLoginMonitor;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mrunknown0001\LaravelLoginMonitor\Contracts\ActivityLogger as ActivityLoggerContract;
use Mrunknown0001\LaravelLoginMonitor\Jobs\SendActivityLog;

final class ActivityLogger implements ActivityLoggerContract
{
    /**
     * @var array<string,mixed>
     */
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $this->mergeConfig($config);
    }

    public function log(string $event, array|Arrayable $payload = []): void
    {
        if (!$this->config['enabled']) {
            return;
        }

        $payload = $payload instanceof Arrayable ? $payload->toArray() : $payload;

        $meta = $this->preparePayload($event, $payload);

        $this->dispatchJob($meta);
    }

    public function logModelEvent(string $event, array $meta = []): void
    {
        $this->log($event, [
            'meta' => $this->withContext($meta),
        ]);
    }

    public function logRequest(array $meta = []): void
    {
        $request = $this->request();

        $this->log('request_activity', [
            'meta' => $this->withContext(array_merge([
                'request_id' => $request?->attributes->get('request_id'),
            ], $meta)),
        ]);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function preparePayload(string $event, array $payload): array
    {
        $base = [
            'event' => $event,
            'timestamp' => Carbon::now()->toIso8601ZuluString(),
            'app' => [
                'name' => config('app.name'),
                'env' => App::environment(),
                'url' => config('app.url'),
            ],
            'context' => $this->context(),
        ];

        $payload = $this->scrubPayload($payload);

        return array_filter(array_merge($base, $payload), static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function dispatchJob(array $payload): void
    {
        $job = new SendActivityLog($payload, $this->config);

        $connection = Arr::get($this->config, 'queue.connection');
        $queue = Arr::get($this->config, 'queue.name');
        $delay = Arr::get($this->config, 'queue.delay');

        if ($connection) {
            $job->onConnection($connection);
        }

        if ($queue) {
            $job->onQueue($queue);
        }

        if ($delay) {
            $job->delay($delay);
        }

        Queue::push($job);
    }

    /**
     * @return array<string,mixed>
     */
    private function context(): array
    {
        $context = [];

        $request = $this->request();

        if ($request) {
            $context['ip'] = $request->ip();
            $context['user_agent'] = $request->userAgent();
            $context['method'] = $request->method();
            $context['url'] = $request->fullUrl();
            $context['request_id'] = $request->headers->get('X-Request-ID') ?? $request->attributes->get('request_id');
        }

        $user = Auth::user();

        if ($user) {
            $context['user'] = [
                'id' => $user->getAuthIdentifier(),
                'type' => $user::class,
                'email' => method_exists($user, 'getEmailForVerification') ? $user->getEmailForVerification() : $user->email ?? null,
            ];
        }

        return array_filter($context, static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string,mixed>  $meta
     * @return array<string,mixed>
     */
    public function withContext(array $meta): array
    {
        return array_filter(array_merge($meta, [
            'context' => array_merge($meta['context'] ?? [], $this->context()),
        ]), static fn ($value) => $value !== null);
    }

    private function request(): ?Request
    {
        return App::bound('request') ? App::make('request') : null;
    }

    /**
     * @param  array<string,mixed>  $config
     * @return array<string,mixed>
     */
    private function mergeConfig(array $config): array
    {
        $defaults = [
            'enabled' => true,
            'endpoint' => null,
            'auth' => [
                'type' => 'token',
                'token' => null,
                'username' => null,
                'password' => null,
                'headers' => [],
            ],
            'queue' => [
                'connection' => null,
                'name' => null,
                'delay' => null,
            ],
            'http' => [
                'timeout' => 10,
                'verify' => true,
                'retry' => [
                    'attempts' => 3,
                    'backoff' => 5,
                    'max_backoff' => 60,
                    'jitter' => true,
                ],
            ],
            'scrub' => [
                'enabled' => true,
                'denylist' => [],
            ],
        ];

        return array_replace_recursive($defaults, $config);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function scrubPayload(array $payload): array
    {
        if (!Arr::get($this->config, 'scrub.enabled', true)) {
            return $payload;
        }

        $deny = collect(Arr::get($this->config, 'scrub.denylist', []))
            ->map(static fn ($key) => Str::lower((string) $key))
            ->filter()
            ->values();

        if ($deny->isEmpty()) {
            return $payload;
        }

        return $this->scrubRecursive($payload, $deny);
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  Collection<int,string>  $deny
     * @return array<string,mixed>
     */
    private function scrubRecursive(array $data, Collection $deny): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->scrubRecursive($value, $deny);

                continue;
            }

            if (is_scalar($value) && $deny->contains(Str::lower((string) $key))) {
                $data[$key] = '***redacted***';
            }
        }

        return $data;
    }

    /**
     * @param  Model  $model
     * @return array<string,mixed>
     */
    public function extractModelMeta(Model $model): array
    {
        return [
            'model' => $model::class,
            'id' => $model->getKey(),
            'table' => $model->getTable(),
        ];
    }
}