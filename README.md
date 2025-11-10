# Laravel Login Monitor

A simple Laravel package to monitor login activities and send beacons/signals to a specific monitoring host.

## Features

- 🔐 Automatic tracking of login successes, failures, and logouts
- 📡 Sends beacons to your monitoring host via HTTP
- ⚙️ Configurable and easy to set up
- 🚀 Non-blocking async requests
- 📊 Captures IP address, user agent, and timestamps

## Installation

Install via Composer:
```bash
composer require mrunknown0001/laravel-login-monitor --no-scripts
```

## Configuration

Publish the configuration file:
```bash
php artisan vendor:publish --tag=login-monitor-config
```

Add to your `.env` file:
```env
LOGIN_MONITOR_HOST=https://your-monitor-host.com
LOGIN_MONITOR_ENABLED=true
```

## Usage

The package automatically tracks authentication events. No additional code needed!

### Manual Beacon Sending
```php
use Murnknown0001\LaravelLoginMonitor\Facades\LoginMonitor;

LoginMonitor::sendBeacon([
    'event' => 'custom_event',
    'data' => 'your custom data'
]);
```

## Beacon Format

Your monitoring host will receive POST requests to `/api/login-monitor` with this structure:
```json
{
    "event": "login_success",
    "user_id": 123,
    "email": "user@example.com",
    "ip_address": "192.168.1.1",
    "user_agent": "Mozilla/5.0...",
    "app_name": "Your App",
    "app_url": "https://yourapp.com",
    "timestamp": "2025-11-10T12:00:00+00:00"
}
```

## Requirements

- PHP 8.0 or higher
- Laravel 9.x, 10.x, 11.x, 12.x

## License

MIT License