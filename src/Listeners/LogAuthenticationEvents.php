<?php

declare(strict_types=1);

namespace Mrunknown0001\LaravelLoginMonitor\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\App;
use Mrunknown0001\LaravelLoginMonitor\Contracts\ActivityLogger;

final class LogAuthenticationEvents
{
    public function __construct(private ActivityLogger $logger)
    {
    }

    public function handle(Login|Failed|Logout $event): void
    {
        if ($event instanceof Login) {
            $this->handleLogin($event);

            return;
        }

        if ($event instanceof Failed) {
            $this->handleFailed($event);

            return;
        }

        if ($event instanceof Logout) {
            $this->handleLogout($event);
        }
    }

    private function handleLogin(Login $event): void
    {
        $user = $event->user;

        $this->logger->log('auth.login', [
            'user' => $this->userMeta($user),
            'meta' => $this->contextMeta(),
        ]);
    }

    private function handleFailed(Failed $event): void
    {
        $this->logger->log('auth.failed', [
            'credentials' => $this->sanitizeCredentials($event->credentials),
            'meta' => $this->contextMeta(),
            'guard' => $event->guard,
        ]);
    }

    private function handleLogout(Logout $event): void
    {
        $user = $event->user;

        $this->logger->log('auth.logout', [
            'user' => $this->userMeta($user),
            'meta' => $this->contextMeta(),
            'guard' => $event->guard,
        ]);
    }

    /**
     * @param  mixed  $user
     * @return array<string,mixed>|null
     */
    private function userMeta($user): ?array
    {
        if (!$user || !method_exists($user, 'getAuthIdentifier')) {
            return null;
        }

        return array_filter([
            'id' => $user->getAuthIdentifier(),
            'class' => $user::class,
            'email' => $user->email ?? null,
        ]);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @return array<string,mixed>
     */
    private function sanitizeCredentials(array $credentials): array
    {
        $deny = array_merge(
            ['password', 'password_confirmation', 'current_password'],
            (array) config('activity-logger.scrub.denylist', [])
        );

        return collect($credentials)
            ->reject(fn ($value, $key) => in_array($key, $deny, true))
            ->all();
    }

    /**
     * @return array<string,mixed>
     */
    private function contextMeta(): array
    {
        $request = App::bound('request') ? App::make('request') : null;

        if (!$request) {
            return [];
        }

        return array_filter([
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
        ]);
    }
}