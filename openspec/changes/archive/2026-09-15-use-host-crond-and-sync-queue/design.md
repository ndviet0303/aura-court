## Context

See `proposal.md` for motivation. This design is required because the change crosses deployment, transaction boundaries, delivery reliability and operations.

- Portainer runs `court-server`, `booking-queue`, `booking-scheduler` and `booking-realtime` from the server image; the application lives at `/app/booking-api`. Root Compose has analogous services, but its backend image is an application artifact, not the final PHP server image. Its executable runtime/path must be verified before documenting a runnable local cron command; do not assume `/var/www/html` from its storage mount.
- The backend requires Laravel `^13.8`. Queue configuration defaults to Redis, production env templates specify database, and the backend env example already specifies sync. Redis remains required for cache and realtime publishing.
- `routes/console.php` schedules pending-booking expiry and expiry notifications each minute, reconciliation at 01:00, invoice notifications daily and overdue release hourly, plus queue-lag monitoring.
- `ProcessOutboxMessage` and `ProcessProviderEvent` opt into after-commit execution and declare eight attempts. Existing records retain error/backoff data, but no recovery schedule currently scans them. Booking mail notifications are also queued and have direct callers.
- Existing `financial-ledger-and-payouts` requirements mandate after-commit external delivery and retries. Changing the driver alone does not preserve those guarantees.

## Goals / Non-Goals

**Goals:** One host schedule owner; no worker/scheduler containers; consistent synchronous dispatch; narrowly scoped recovery using existing durable records; reversible rollout.

**Non-Goals:** New broker, daemon, delivery framework, provider integration, database cleanup, redesign of financial flows, removal of Redis/realtime, or automatic modification of a production host. Do not promise exactly-once delivery to external mail systems; preserve existing logical business idempotency.

## Decisions

### 1. Host crond invokes the application, not a new container

Provide a host crontab example and installation/verification runbook. Every minute it invokes `php artisan schedule:run` through non-interactive container execution, explicitly selecting `court-server`, `/app/booking-api` and an application user with correct storage permissions. The host entry contains no application secrets: execution inherits the running container's environment. Record actual Docker binary location, cron user and logging destination in the deployment runbook rather than assuming the interactive shell's PATH.

Keep schedules in Laravel, with application timezone unchanged. Use named per-task overlap locks for protected closures and scheduled recovery; do not put a global lock around all schedules because long reconciliation must not suppress expiry checks. Log invocation output/failures with rotation. Exactly one host installs the entry for each environment. Host downtime does not automatically replay every missed daily/hourly schedule; the runbook identifies missed work and provides an explicit, duplicate-safe reconciliation procedure.

Alternative rejected: `crond` inside a scheduler container or another process inside the web container contradicts the selected host-owned topology and adds lifecycle management.

### 2. Configure sync once at every supported entry point

Change the queue fallback, stack default and production templates to `sync`; retain the existing sync env example. Set the sync connection's `after_commit` behavior to preserve external-delivery transaction boundaries. Keep job classes and dispatch calls unless a delivery boundary needs adjustment. Remove worker startup from `composer dev`, both deployment worker services, both scheduler services and the queue-depth schedule for sync mode. Retain old connection definitions and historical job tables for controlled drain/rollback.

Deployment verification must inspect effective configuration, not just template files: existing Portainer overrides can still select Redis/database, and cached configuration can retain old values.

Alternative rejected: replacing every job with direct service calls would create unnecessary code changes and bypass existing job semantics.

### 3. Recover durable failures through bounded scheduled work

Add a small scheduled recovery entry for due unpublished outbox records and unprocessed persisted provider events. Reuse existing handlers and their backoff fields; process bounded batches each minute, isolate each item failure, and preserve tenant context. Use atomic ownership/locks shared by immediate and recovery paths where they could race. Reclaim expired outbox processing claims after a documented lease longer than the enforced execution bound. Preserve an eight-attempt automatic limit for these existing jobs using persisted attempt counts, with exhausted records retained and surfaced for manual intervention. The sync driver's job attempt metadata is not a retry scheduler.

Retain existing provider financial idempotency checks, and test both duplicate callbacks and callback/recovery races. Do not extend the placeholder provider-outbox integration or wire unrelated payout recovery in this change.

For booking notifications affected by worker removal, persist retryable delivery intent through the existing outbox and deliver after commit. Preserve the current notification payload and mail view. Track database/mail delivery progress separately where needed so retrying failed mail does not duplicate an already delivered database notification. This is a focused adaptation of existing records/handlers, not a new generic queue implementation.

Catch secondary delivery failures only at a boundary where durable intent is already committed: log and retain retry state, then preserve the successful booking/payment response. Do not swallow primary validation, database or financial-processing errors. Verified provider-event processing retains its existing failure response while leaving the event available for scheduled retry.

Alternative rejected: removing workers and relying solely on provider redelivery leaves notification/outbox failures stranded and violates existing retry requirements. Keeping workers selectively conflicts with the agreed scope.

### 4. Preserve schedule behavior under synchronous workloads

Keep existing business frequencies. Reconciliation now executes organization work synchronously; isolate an organization's failure so remaining organizations can run, record failures, and document rerun steps. Measure runtime and request latency with successful and failing mail/provider calls; keep external I/O bounded. Do not use a blanket scheduler timeout that silently cuts off business work. A missed interval, expired lock or permanently failing delivery must be visible in logs/existing observability, rather than hidden behind a zero queue-depth metric.

## Risks / Trade-offs

- [Longer requests and scheduled runs] → Measure representative checkout/webhook latency and scheduled runtime against existing operational limits before rollout; retain finite external I/O timeouts and task-level overlap control.
- [Committed operation followed by a delivery exception] → Durable intent plus boundary-specific exception handling; regression-test the successful business response and eventual delivery.
- [Interrupted or duplicate delivery] → Expiring claims, per-channel progress and existing idempotency keys; external mail can still duplicate after an ambiguous provider acknowledgement.
- [Cron has Docker-level host privileges] → Limit installation to the designated operator/host, use the application user inside the container and avoid secrets in crontabs/logs.
- [Cron failure or stale runtime env] → Verify a real tick and resolved sync configuration after deployment; retain invocation failures and missed-schedule procedures.
- [Root Compose is not currently a verified PHP runtime] → Check its intended startup path before changing it; keep any required correction limited to enabling the documented application execution path, and flag broader runtime repair separately.

## Migration Plan

1. Build and test the application/configuration changes in an isolated environment. Prepare, but do not enable, the host cron entry. Record previous image, effective queue connection, queue names and configuration-cache procedure.
2. Pause new work producers for the cutover window, including the old scheduler and inbound writes/webhooks as appropriate. Inventory the actual old connection's pending, reserved, delayed and failed work. Drain with the previous worker configuration, or explicitly account for retained items and their recovery path; never flush queues to make the count zero.
3. Stop the old workers after in-flight work completes. Deploy the topology without queue/scheduler services, set the actual production override to sync, refresh cached configuration and verify the application's effective connection.
4. Enable the single host cron entry only after the old scheduler is stopped. Verify task registration, one real tick, container-unavailable logging, permissions and application timezone. Resume traffic and observe delivery/recovery and financial idempotency.
5. Roll back by pausing producers, disabling host cron and allowing active recovery to finish, then restoring the recorded image/configuration and old worker/scheduler services. Restore only one scheduling owner, reconcile recovery state and resume traffic. Preserve all historical and newly committed data.

Host deployment actions require a separate explicit deployment request; implementation completion alone does not authorize installing cron or altering Portainer.

## References

- Laravel 13 scheduler documentation, retrieved through Context7 `/laravel/docs/__branch__13.x`: https://github.com/laravel/docs/blob/13.x/scheduling.md
- Laravel 13 queue/transaction documentation, retrieved through the same source: https://github.com/laravel/docs/blob/13.x/queues.md
- Local `vendor/laravel/framework/src/Illuminate/Queue/SyncQueue.php` confirms immediate execution, after-commit callbacks, exception propagation and the absence of worker retry behavior.
