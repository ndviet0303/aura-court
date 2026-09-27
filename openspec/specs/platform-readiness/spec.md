# Platform Readiness Specification

## Purpose

Establishes verifiable operational, migration, and payment-readiness gates before
financial marketplace features are exposed to production tenants.

## Requirements

### Requirement: PSP and operating-model go-no-go gate
Before enabling direct-owner collection, split payments, paid transfers or paid tournament registrations for a cohort, the platform SHALL record verified provider evidence for connection isolation, receiving-account control, authenticated callbacks, receipt matching, deduplication, late/unknown outcomes and owner-executed refund tracking. Subscription verification SHALL use its separate receiver. New direct flows MUST NOT require unsupported automatic bank refunds/payouts or pretend those capabilities exist. Legacy payouts SHALL retain their own provider/operating gate. Accountable operational owners and exception procedures SHALL be recorded.

#### Scenario: All required PSP capabilities are verified
- **WHEN** sandbox and isolated acceptance prove the required direct-money controls and owner refund procedure
- **THEN** only the approved cohort/capabilities SHALL be enabled

#### Scenario: A required PSP capability is unavailable
- **WHEN** a required callback or receiving-account verification cannot be demonstrated
- **THEN** that financial capability SHALL remain blocked with the missing evidence recorded

#### Scenario: Unsupported automatic debit
- **WHEN** a provider lacks automatic owner-bank refunds
- **THEN** the system SHALL use truthful external owner-refund obligations, not fake successful automated refunds

### Requirement: Rehearsed data migration and rollback
The platform SHALL provide a rehearsed migration plan that backfills each legacy venue into an organization and preserves legacy booking history and `/api/v1` responses. The plan MUST include counts/checksums, a defined dual-read or compatibility period, and a tested rollback path before pilot data is migrated.

#### Scenario: Migration rehearsal succeeds
- **WHEN** a representative production-like data copy is migrated in rehearsal
- **THEN** the recorded source and target counts/checksums SHALL reconcile and legacy booking responses SHALL remain available

#### Scenario: Rehearsal detects a mismatch
- **WHEN** reconciliation detects a missing or altered legacy record
- **THEN** pilot migration SHALL be blocked and the rollback procedure SHALL restore the prior serving path

### Requirement: Baseline reliability and integrity evidence
Before pilot activation, the platform SHALL produce repeatable test evidence for 200 peak read requests per second, 25 booking/payment write transactions per second, 2,000 concurrent sessions, and 100 concurrent attempts for one court-time. The evidence MUST show one valid active reservation and 99 valid conflicts in the final concurrency case, and must include a backup-restore rehearsal meeting RPO of five minutes and RTO of 60 minutes.

#### Scenario: Baseline test meets targets
- **WHEN** the baseline load, concurrency, and recovery tests are run against the pilot configuration
- **THEN** the recorded result SHALL meet the stated integrity and recovery thresholds

#### Scenario: Baseline test fails a target
- **WHEN** a target is missed or booking integrity is violated
- **THEN** pilot rollout SHALL be blocked until a successful rerun records the correction

### Requirement: Controlled phase rollout
The platform SHALL expose each phase to an explicitly approved pilot cohort through independently reversible rollout controls. A phase SHALL not be expanded until its phase-entry evidence and acceptance tests are recorded as passed.

#### Scenario: Pilot cohort is approved
- **WHEN** a phase's entry criteria and pilot organizations are approved
- **THEN** only that cohort SHALL receive the phase capability

#### Scenario: Pilot incident requires rollback
- **WHEN** a material integrity, security, or financial incident is declared during a pilot
- **THEN** the affected capability SHALL be disabled without removing historical records or changing settled ledger entries

### Requirement: Seven functional area acceptance evidence
Completion SHALL require a persisted traceability matrix for all 126 FR requirements and D01–D71 audit findings across seven modules. Every finding SHALL be reproduced or explicitly corrected by evidence and mapped to a specification, implementation task and acceptance result. Each area SHALL cover real API success/reload, negative inputs, role/venue/tenant isolation, retries/races and monetary reconciliation where applicable. Provider-dependent flows SHALL not be marked operational using mocks. Undefined report NFR/data/UC details SHALL remain explicit unknowns and not be claimed complete.

#### Scenario: Full acceptance
- **WHEN** all seven modules and D01–D71 have implementation and evidence
- **THEN** release SHALL record environment, source revision, tests, remaining operational gates and 126-FR coverage

#### Scenario: Finding disproven
- **WHEN** a previously static finding does not reproduce after baseline investigation
- **THEN** its disposition SHALL cite contrary code/runtime evidence and retain the audit record instead of implementing a needless change

#### Scenario: Mock-only flow
- **WHEN** a required payment journey passes only a fake-response UI test
- **THEN** the feature SHALL remain incomplete for operational acceptance

### Requirement: Independent package rollout
Each delivery package SHALL have independently reversible activation and recorded entry/exit evidence. Rollback SHALL preserve completed payments, attendance and historical records and SHALL NOT restore false-success paths.

#### Scenario: Package rollback
- **WHEN** a POS integrity failure is found in pilot
- **THEN** new affected operations are disabled without deleting committed receipts or journals

### Requirement: Non-destructive direct-payment cutover
Rollout SHALL inventory and reconcile wallet balances, pending deposits, bookings, entitlements, refunds, transfers, journals and payouts before changing each cohort. Historical records SHALL retain explicit payment-model identity and original references; no automatic conversion, deletion, retroactive terms or transfer of funds is authorized. Legacy liability disposition SHALL be reviewed by the accountable owner before cohort activation. Rollback SHALL stop new affected commands while preserving committed receipts, access to obligations and event recovery; it MUST NOT re-enable false-success or silently restore commission/custody for direct receipts.

#### Scenario: Outstanding balance
- **WHEN** a candidate cohort has unexplained wallet or pending payout liabilities
- **THEN** activation SHALL be blocked until a recorded disposition and reconciliation exists

#### Scenario: Rollback after direct receipt
- **WHEN** an incident occurs after a direct owner payment was received
- **THEN** new collection SHALL pause while receipt history, servicing/refund access and reconciliation remain available without reinterpreting the money as platform-held
