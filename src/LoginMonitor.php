<?php

namespace Mrunknown0001\LaravelLoginMonitor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LoginMonitor
{
    protected $host;
    protected $enabled;

    public function __construct($host, $enabled = true)
    {
        $this->host = $host;
        $this->enabled = $enabled;
    }

    public function sendBeacon(array $data)
    {
        if (!$this->enabled) {
            return;
        }

        try {
            Http::timeout(5)
                ->post($this->host . '/api/login-monitor', array_merge($data, [
                    'app_name' => config('app.name'),
                    'app_url' => config('app.url'),
                    'timestamp' => now()->toIso8601String(),
                ]));
        } catch (\Exception $e) {
            Log::warning('Login monitor beacon failed: ' . $e->getMessage());
        }
    }

    public function loginSuccess($user)
    {
        $this->sendBeacon([
            'event' => 'login_success',
            'user_id' => $user->id ?? 'User ID not Defined',
            'email' => $user->email ?? 'Email not found',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function loginFailed($credentials)
    {
        $this->sendBeacon([
            'event' => 'login_failed',
            'email' => $credentials['email'] ?? 'unknown',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function logout($user)
    {
        $this->sendBeacon([
            'event' => 'logout',
            'user_id' => $user->id ?? 'User ID not Defined',
            'email' => $user->email ?? 'Email not found',
            'ip_address' => request()->ip(),
        ]);
    }

    public function recordCreated(Model $model): void
    {
        $this->emitRecordEvent('create', $this->modelMeta($model, [
            'attributes' => $model->getAttributes(),
        ]));
    }

    public function recordUpdated(Model $model): void
    {
        $this->emitRecordEvent('update', $this->modelMeta($model, [
            'changes' => $model->getChanges(),
            'original' => $model->getOriginal(),
        ]));
    }

    public function recordDeleted(Model $model): void
    {
        $this->emitRecordEvent('delete', $this->modelMeta($model, [
            'original' => $model->getOriginal(),
        ]));
    }

    public function recordQueryBuilderOperation(string $operation, array $meta = []): void
    {
        $this->emitRecordEvent($operation, $meta);
    }

    protected function emitRecordEvent(string $operation, array $meta = []): void
    {
        $event = match ($operation) {
            'create' => 'record_created',
            'update' => 'record_updated',
            'delete' => 'record_delete',
            default => $operation,
        };

        $metaPayload = array_merge(['operation' => $operation], $meta);

        $this->sendBeacon([
            'event' => $event,
            'meta' => $this->enrichMeta($metaPayload),
        ]);
    }

    protected function modelMeta(Model $model, array $extra = []): array
    {
        return array_merge([
            'table' => $model->getTable(),
            'record_id' => $model->getKey(),
            'model_class' => get_class($model),
        ], $extra);
    }

    protected function enrichMeta(array $meta = []): array
    {
        $context = $this->contextMeta();

        if (!empty($context)) {
            $meta['context'] = array_merge($context, $meta['context'] ?? []);
        }

        return array_filter($meta, static function ($value) {
            return !is_null($value) && $value !== '';
        });
    }

    protected function contextMeta(): array
    {
        $meta = [];

        if (app()->bound('auth')) {
            $user = Auth::user();

            if ($user) {
                $meta['acting_user_id'] = $user->getAuthIdentifier();
                $meta['acting_user_class'] = get_class($user);
            }
        }

        $request = $this->currentRequest();

        if ($request) {
            $meta['ip_address'] = $request->ip();
            $meta['user_agent'] = $request->userAgent();
            $meta['url'] = $request->fullUrl();
            $meta['http_method'] = $request->method();
        }

        return $meta;
    }

    protected function currentRequest(): ?Request
    {
        if (!app()->bound('request')) {
            return null;
        }

        $request = app('request');

        return $request instanceof Request ? $request : null;
    }
}