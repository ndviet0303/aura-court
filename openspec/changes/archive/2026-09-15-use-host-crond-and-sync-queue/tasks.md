## 1. Runtime configuration and topology

- [x] 1.1 Verify the final server image's PHP executable, application path and runtime user; resolve the root Compose artifact/runtime mismatch only as needed for its documented application execution path, and record the verified cron targets.
- [x] 1.2 Standardize the queue fallback, Portainer default and production env templates on `sync`, enable after-commit behavior for sync, and retain previous queue connections/history for drain and rollback.
- [x] 1.3 Remove queue and scheduler services from root Compose and Portainer, remove the development queue-listener startup, and verify Redis/realtime and other unrelated services remain unchanged.

## 2. Delivery correctness and recovery

- [x] 2.1 Trace affected notification, outbox and provider-event dispatch boundaries; add focused regression tests for immediate sync execution, nested transaction commit and rollback suppression of external delivery.
- [x] 2.2 Route affected booking notification delivery through existing durable outbox handling while preserving payloads/mail views and channel-level progress; retain a successful booking/payment response after durably recorded secondary delivery failure without swallowing primary errors.
- [x] 2.3 Add bounded scheduled recovery for due unpublished outbox and unprocessed provider events, using existing handlers/backoff, persisted eight-attempt limits, per-item failure isolation and visible exhausted records.
- [x] 2.4 Coordinate immediate/recovery execution ownership, document execution bounds and claim expiry, and recover stale claims without duplicate logical financial effects or cross-tenant processing.

## 3. Host scheduling and operational guidance

- [x] 3.1 Preserve existing business frequencies/timezone, add named task-level overlap protection, isolate reconciliation failures per organization, and remove queue-depth scheduling for sync mode.
- [x] 3.2 Add host-crond examples and a runbook for minute-based, non-interactive execution inside the application container, including binary paths, runtime user, single-host ownership, logging/rotation, missing-container failures and missed-task recovery. Do not install host cron as part of repository implementation.
- [x] 3.3 Update root/backend READMEs, relevant production templates and monitoring guidance to describe sync latency/failures and worker-free recovery rather than recommend persistent worker/scheduler processes.
- [x] 3.4 Document cutover and rollback with producer pause, actual old-connection backlog inventory/drain, retained failed history, in-flight completion, production override/config-cache checks and mutually exclusive schedule ownership.

## 4. Verification and handoff

- [x] 4.1 Test transient mail/outbox failure followed by scheduled success, per-channel retry without duplicate database notifications, backoff eligibility, attempt exhaustion, stale claims and continuation past a failing item.
- [x] 4.2 Test duplicate provider callbacks and callback/recovery races for one tenant-scoped financial outcome, plus visible primary-processing failures and durable retry state.
- [x] 4.3 Test unchanged business schedule registration, task overlap behavior and reconciliation continuation; in an isolated runnable environment verify a host-style tick, permissions and container-unavailable logging without queue/scheduler services.
- [x] 4.4 Validate both deployment definitions using non-secret test values, effective sync configuration and absence of worker startup; run focused backend regressions and measure representative checkout/webhook and scheduled-work latency against existing operational limits.
- [x] 4.5 Rehearse backlog cutover/rollback in an isolated environment without deleting retained work; record verification evidence and clearly separate repository completion from production deployment steps requiring operator authorization.
