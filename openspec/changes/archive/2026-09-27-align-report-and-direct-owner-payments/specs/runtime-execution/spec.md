## ADDED Requirements

### Requirement: Complete business expiry and recovery
Existing host-owned scheduling SHALL invoke valid services for V1/V2 holds, split/recurring obligations, pending POS reservations and subscription lifecycle. Expiry SHALL commit durably despite response errors, release only eligible unpaid inventory/benefits and reconcile late receipts. Processing SHALL be bounded, retry-safe, tenant-scoped and observable without adding a dedicated worker/scheduler requirement.

#### Scenario: V2 hold abandoned
- **WHEN** no client revisits an expired V2 hold
- **THEN** the scheduled sweep SHALL release it and its unconsumed benefits once

#### Scenario: Scheduler service resolution
- **WHEN** the deployed host tick runs all due business schedules
- **THEN** every registered callback SHALL resolve and invocation failures SHALL be visible

#### Scenario: Payment races expiry
- **WHEN** callback/recovery and expiry process one obligation concurrently
- **THEN** one serializable allocation or reconciliation outcome SHALL persist without duplicate release/payment

#### Scenario: Recurring or QR expiry
- **WHEN** an unpaid future invoice or QR order expires
- **THEN** only its eligible inventory/stock reservations SHALL release while received-money obligations remain tracked

