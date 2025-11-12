# Laravel Login Monitor

A simple Laravel package to monitor login activities and database mutations, sending beacons to a designated monitoring host.

## Features

- 🔐 Automatic tracking of login successes, failures, and logouts
- 🗃️ Observes create, update, and delete operations across Eloquent models and raw/query builder mutations
- 📡 Sends beacons to your monitoring host via HTTP
- 🧠 Enriches each beacon with contextual metadata (acting user, request details, table names, record identifiers)
- 🌐 Captures IP address, user agent, full URL, HTTP method, and timestamps
- ⚙️ Configurable and easy to set up
- 🚀 Non-blocking async requests

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

The package automatically tracks authentication events and database mutations (create, update, delete) initiated through Eloquent models or the query builder. No additional instrumentation is required.

### Manual Beacon Sending

```php
use Mrunknown0001\LaravelLoginMonitor\Facades\LoginMonitor;

LoginMonitor::sendBeacon([
    'event' => 'custom_event',
    'data' => 'your custom data',
]);
```

### Events Captured

| Event | Triggered when | Meta payload highlights |
| --- | --- | --- |
| `login_success` | A user successfully authenticates | `user_id`, `email`, request context (IP, UA, HTTP method, URL) |
| `login_failed` | An authentication attempt fails | `email`, request context |
| `logout` | A user logs out | `user_id`, request context |
| `record_created` | A record is inserted via Eloquent or the query builder | `table`, `record_id`, `attributes`, actor + request context |
| `record_updated` | A record is updated via Eloquent or the query builder | `table`, `record_id`, `changes`, `original`, actor + request context |
| `record_delete` | A record is deleted via Eloquent or the query builder | `table`, `record_id`, `original`, actor + request context |

> **Note:** Query builder mutations are automatically detected while avoiding duplicate beacons for Eloquent operations.

### Example: Manual Mutation Recording

```php
use Mrunknown0001\LaravelLoginMonitor\Facades\LoginMonitor;

/** @var \Illuminate\Database\Eloquent\Model $model */
LoginMonitor::recordCreated($model);

// For non-model contexts (jobs, scripts) using the query builder:
LoginMonitor::recordQueryBuilderOperation('update', [
    'table' => 'orders',
    'record_id' => 42,
    'changes' => ['status' => 'shipped'],
]);
```

## Beacon Format

All beacons are POSTed to `/api/login-monitor` with the following base structure:

- `event`: The event name (e.g., `login_success`, `record_updated`)
- `meta`: Contextual payload gathered for the event
- `app_name`, `app_url`, `timestamp`: Automatic application metadata

### Example: Login Success

```json
{
    "event": "login_success",
    "user_id": 123,
    "email": "user@example.com",
    "ip_address": "192.168.1.1",
    "user_agent": "Mozilla/5.0 ...",
    "app_name": "Your App",
    "app_url": "https://yourapp.com",
    "timestamp": "2025-11-10T12:00:00+00:00"
}
```

### Example: Record Update

```json
{
    "event": "record_updated",
    "meta": {
        "operation": "update",
        "table": "orders",
        "record_id": 42,
        "changes": {
            "status": "shipped"
        },
        "original": {
            "status": "processing"
        },
        "context": {
            "acting_user_id": 1,
            "acting_user_class": "App\\Models\\User",
            "ip_address": "192.168.1.1",
            "user_agent": "Mozilla/5.0 ...",
            "url": "https://yourapp.com/orders/42",
            "http_method": "PUT"
        }
    },
    "app_name": "Your App",
    "app_url": "https://yourapp.com",
    "timestamp": "2025-11-10T12:00:00+00:00"
}
```

## Requirements

- PHP 8.1 or higher
- Laravel 9.x, 10.x, 11.x, 12.x

## License

MIT License
