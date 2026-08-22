## Why

Aura Court is a single-tenant booking demo with globally scoped administration and an inbound-only payment flow. To operate a reliable marketplace for multiple court operators, the platform must establish tenant isolation and financial controls before adding revenue-generating booking, social, or POS features.

This change defines a gated, phased path from the existing `/api/v1` booking experience to a multi-tenant SaaS marketplace while preserving legacy API behaviour during migration.

## What Changes

- Introduce a Phase 0 readiness gate that verifies PSP capabilities and operating ownership, produces a reversible tenant/data migration plan, and baselines load and recovery behaviour before financial features proceed.
- Add organizations, scoped memberships, role-based access, owner operations, and tenant-isolation controls.
- Add `/api/v2` reservation and pricing capabilities with immutable quotes, while keeping `/api/v1` available for legacy booking clients.
- Add an immutable double-entry ledger, settlement and payout workflows, provider abstraction, command idempotency, transactional outbox, reconciliation, and Redis-backed asynchronous/realtime infrastructure.
- Add advanced booking flows: long-running recurrence with monthly billing, split payment, and controlled slot resale.
- Add player profiles, matchmaking, reputation/disputes, and single-elimination/round-robin tournament operations with versioned realtime events.
- Add tenant POS, inventory and shift reconciliation, versioned memberships, loyalty, and closed-loop tenant wallet credits.
- **BREAKING (new API only):** all new business commands are exposed under `/api/v2`, require an `Idempotency-Key`, and are authorized in an organization context. `/api/v1` remains supported through the migration window.

## Capabilities

### New Capabilities

- `platform-readiness`: PSP/compliance go-no-go evidence, migration rehearsal, operational baselines, and phase-entry gates.
- `organization-access`: organization tenancy, scoped roles, owner operations, and tenant-isolation enforcement.
- `reservations-and-pricing`: immutable price quotes and `/api/v2` reservation inventory that coexists with legacy booking clients.
- `financial-ledger-and-payouts`: immutable accounting, payment-provider operations, settlement, payout, reconciliation, and after-commit delivery.
- `advanced-booking-marketplace`: recurring billing, split collection/refunds, and constrained booking-slot transfers.
- `social-tournaments`: player discovery, reputation/disputes, and realtime tournament administration.
- `point-of-sale-and-loyalty`: tenant POS, inventory and shifts, memberships, loyalty, and closed-loop wallet credit.

### Modified Capabilities

- None; the repository has no existing OpenSpec capability specifications.

## Impact

- Backend: Laravel domain models, migrations, policies, API routes/controllers, workers, notifications, observability, and automated concurrency/authorization tests.
- Frontend: Vue owner portal, customer flows, POS, social/tournament views, and realtime state handling.
- Interfaces: `/api/v2`, PSP adapter contract, idempotency protocol, webhook processing, event channels, and export/report contracts.
- Infrastructure: managed PostgreSQL/Redis, stateless API/worker/realtime processes, secure secret/KYC storage, monitoring, backup/restore, and load-test tooling.
- Delivery: Phase 0 approval is a prerequisite for finance-dependent work; pilot tenants and feature flags limit rollout risk.
