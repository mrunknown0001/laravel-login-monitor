<?php

namespace Mrunknown0001\LaravelLoginMonitor;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LoginMonitor
{
    protected $host;
    protected $enabled;

    public function __construct($host = 'http://localhost:8001', $enabled = true)
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
}