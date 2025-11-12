# Architecture Overview

This document outlines the design for the asynchronous activity logging package.

## Goals

- Capture authentication and arbitrary activity events from Laravel applications.
- Dispatch payloads to a remote HTTP API without blocking user requests.
- Provide configurable delivery, authentication, and retry semantics.
- Offer extensible hooks for custom events and metadata enrichment.

## High-Level Flow

1. Application code invokes the activity logger via middleware, traits, or direct facade usage.
2. The logger resolves configuration, builds the payload, and dispatches a queue job.
3. The queued job performs the HTTP call using the configured client, handling retries and failure reporting.
4. Failed jobs are surfaced through Laravel's queue failure channels for observability.

## Package Components

- **Configuration (`config/activity-logger.php`)**: Defines endpoint URL, auth credentials, queue connection, queue name, timeout, retry attempts, and backoff strategy.
- **Service Provider (`src/ActivityLoggerServiceProvider.php`)**: Publishes config, registers bindings, and wires middleware + event listeners.
- **Contracts (`src/Contracts/ActivityLogger.php`)**: Defines interface for logging service, facilitating custom implementations.
- **Logger Implementation (`src/ActivityLogger.php`)**: Collects request context, serializes payload, and dispatches queue job.
- **HTTP Job (`src/Jobs/SendActivityLog.php`)**: Implements `ShouldQueue`, uses Laravel HTTP client with retry/backoff, handles auth headers, and records failures.
- **Middleware (`src/Http/Middleware/LogRequestActivity.php`)**: Optionally records per-request activity or hooks into specific routes.
- **Model Trait (`src/Concerns/LogsActivity.php`)**: Provides helpers to record create/update/delete events with contextual metadata.
- **Event Listener (`src/Listeners/LogAuthenticationEvents.php`)**: Listens to authentication events and funnels them through the logger.
- **Facade (`src/Facades/ActivityLogger.php`)**: Offers expressive static interface for package consumers.
- **Testing Utilities (`tests/TestCase.php`, `tests/Feature/...`)**: Bootstraps Orchestra Testbench and covers dispatch logic + HTTP retry behavior.

## Configuration Keys

- `endpoint`: Remote API base URL (required).
- `token`: Optional bearer token or API key for authentication.
- `timeout`: Request timeout in seconds.
- `retry`: Structured array for attempts, backoff, and jitter configuration.
- `queue`: Connection, queue name, and job-specific options.
- `features`: Toggles for middleware auto-registration or auth event logging.

## Security Considerations

- Credentials are loaded from environment variables and never hard-coded.
- Payloads are sanitized to avoid leaking sensitive fields; consumers can define allow/deny lists.
- HTTP client enforces TLS verification and supports rotating tokens.

## Error Handling

- Job retries follow exponential backoff with configurable max attempts.
- Failures emit Laravel queue failure events allowing downstream alerts.
- Job logs include correlation IDs for traceability.

## Extensibility

- Developers can bind custom implementations to the logger contract.
- Additional metadata hooks are exposed via events and configurable pipelines.
- Middleware and traits are optional; direct facade usage remains available.

## Deployment Notes

- Requires configured queue worker (e.g., Redis, SQS) matching package queue settings.
- Supports Horizon or Supervisor-managed workers for resilience.
- Documented health checks ensure remote endpoint availability before deployment.