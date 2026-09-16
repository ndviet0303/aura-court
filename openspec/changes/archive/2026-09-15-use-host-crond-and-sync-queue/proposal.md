## Why

The deployment runs dedicated Laravel scheduler and queue containers even though the desired operating model is host `crond` with synchronous job execution. Queue defaults also disagree across application configuration and deployment templates, making runtime behavior depend on the entry point.

## What Changes

- **BREAKING (operations):** Remove the scheduler and queue services from both the Portainer stack and root Compose definition. Remove queue-worker startup from the development script.
- Run exactly one host `crond` entry per environment, invoking Laravel `schedule:run` every minute inside the existing application container. Keep Laravel as the source of business schedules.
- **BREAKING (execution):** Standardize `QUEUE_CONNECTION=sync` across defaults, templates and deployment instructions; dispatched jobs execute in the calling process without a persistent worker.
- Preserve after-commit delivery, durable retry of failed side effects and idempotent payment handling, using existing persisted outbox/provider records and bounded scheduled recovery rather than another worker service.
- Remove obsolete queue-depth scheduling for sync mode; document cron failures, job failures, backlog drain, cutover and rollback.
- Retain Redis, the realtime service, all existing business schedule frequencies and historical queue/financial data.

## Capabilities

### New Capabilities

- `runtime-execution`: Host-triggered scheduling, synchronous queue execution, failure recovery and safe migration without scheduler/queue containers.

### Modified Capabilities

None. Existing after-commit delivery, retry, tenant isolation, financial idempotency and reconciliation requirements remain binding; this change replaces their execution mechanism, not their guarantees.

## Impact

- Deployment: `docker-compose.yml`, `devops/portainer/booking_app.stack.yml`, production env templates, and a host-cron example/runbook targeting `court-server` at `/app/booking-api`.
- Backend: `config/queue.php`, `routes/console.php`, `composer.json`, and narrowly scoped delivery/recovery changes around existing jobs, outbox, provider events and booking notifications.
- Operations/documentation: root/backend READMEs and affected monitoring guidance; production env overrides and cached configuration must be checked during deployment.
- Tests: schedule registration and overlap, sync dispatch/transaction boundaries, transient failure recovery, duplicate payment/delivery protection and topology/configuration consistency.
- No new runtime dependency, public API endpoint, payment-provider integration, Redis removal or host mutation is authorized by this proposal. Request latency can increase with synchronous work and must be measured before rollout.
