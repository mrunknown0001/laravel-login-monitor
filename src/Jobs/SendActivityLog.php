<?php

declare(strict_types=1);

namespace Mrunknown0001\LaravelLoginMonitor\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\ManuallyFailedException;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendActivityLog implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var array<string,mixed>
     */
    private array $payload;

    /**
     * @var array<string,mixed>
     */
    private array $config;

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $config
     */
    public function __construct(array $payload, array $config)
    {
        $this->payload = $payload;
        $this->config = $config;
    }

    public function handle(): void
    {
        if (!Arr::get($this->config, 'enabled', true)) {
            return;
        }

        $endpoint = (string) Arr::get($this->config, 'endpoint');

        if ($endpoint === '') {
            Log::warning('ActivityLogger: endpoint is not configured, skipping payload dispatch.');

            return;
        }

        $http = Http::withOptions([
            'timeout' => (int) Arr::get($this->config, 'http.timeout', 10),
            'verify' => filter_var(Arr::get($this->config, 'http.verify', true), FILTER_VALIDATE_BOOL),
        ]);

        $http = $this->applyAuth($http);

        $retryConfig = Arr::get($this->config, 'http.retry', []);

        $attempts = max(1, (int) Arr::get($retryConfig, 'attempts', 3));
        $baseBackoff = max(1, (int) Arr::get($retryConfig, 'backoff', 5));
        $maxBackoff = max($baseBackoff, (int) Arr::get($retryConfig, 'max_backoff', 60));
        $jitter = filter_var(Arr::get($retryConfig, 'jitter', true), FILTER_VALIDATE_BOOL);

        $url = rtrim($endpoint, '/');
        $lastException = null;
        $response = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $http->post($url, $this->payload);

                if ($response->successful()) {
                    return;
                }

                if (!$this->shouldRetryResponse($response) || $attempt === $attempts) {
                    break;
                }
            } catch (Throwable $exception) {
                $lastException = $exception;

                if ($attempt === $attempts) {
                    break;
                }
            }

            $delaySeconds = $this->calculateBackoffDelay($baseBackoff, $attempt + 1, $maxBackoff, $jitter);
            usleep((int) ($delaySeconds * 1_000_000));
        }

        if ($lastException) {
            Log::error('ActivityLogger: exception while dispatching payload.', [
                'exception' => $lastException,
                'payload' => $this->payload,
            ]);

            $this->fail($lastException);

            return;
        }

        if ($response) {
            $message = sprintf(
                'ActivityLogger: payload dispatch failed with status %s.',
                $response->status()
            );

            Log::error($message, [
                'payload' => $this->payload,
                'response_body' => $response->body(),
            ]);

            $this->fail(new ManuallyFailedException($message));
        }
    }

    /**
     * @param  \Illuminate\Http\Client\PendingRequest  $http
     * @return \Illuminate\Http\Client\PendingRequest
     */
    private function applyAuth($http)
    {
        $authType = Arr::get($this->config, 'auth.type', 'token');

        $headers = Arr::get($this->config, 'auth.headers', []);
        $http = $http->withHeaders($headers);

        return match ($authType) {
            'token' => $this->applyTokenAuth($http),
            'basic' => $this->applyBasicAuth($http),
            'none' => $http,
            default => $http,
        };
    }

    /**
     * @param  \Illuminate\Http\Client\PendingRequest  $http
     * @return \Illuminate\Http\Client\PendingRequest
     */
    private function applyTokenAuth($http)
    {
        $token = Arr::get($this->config, 'auth.token');

        return $token ? $http->withToken($token) : $http;
    }

    /**
     * @param  \Illuminate\Http\Client\PendingRequest  $http
     * @return \Illuminate\Http\Client\PendingRequest
     */
    private function applyBasicAuth($http)
    {
        $username = Arr::get($this->config, 'auth.username');
        $password = Arr::get($this->config, 'auth.password');

        if ($username && $password) {
            return $http->withBasicAuth($username, $password);
        }

        return $http;
    }

    /**
     * @param  \Illuminate\Http\Client\Response  $response
     */
    private function shouldRetryResponse($response): bool
    {
        if ($response->serverError()) {
            return true;
        }

        return in_array($response->status(), [408, 423, 425, 429], true);
    }

    private function calculateBackoffDelay(int $base, int $attempt, int $maxBackoff, bool $jitter): int
    {
        $exponent = max(0, $attempt - 1);

        $delay = min($maxBackoff, (int) ($base * (2 ** $exponent)));

        if ($jitter) {
            $min = max(1, (int) floor($delay / 2));
            $max = max($min, $delay);

            $delay = random_int($min, $max);
        }

        return max(1, $delay);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ActivityLogger: job permanently failed.', [
            'exception' => $exception,
            'payload' => $this->payload,
        ]);
    }
}