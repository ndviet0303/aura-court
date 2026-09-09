# Platform Readiness Specification

## Purpose

Establishes verifiable operational, migration, and payment-readiness gates before
financial marketplace features are exposed to production tenants.

## Requirements

### Requirement: PSP and operating-model go-no-go gate
The platform SHALL record a Phase 0 go-no-go decision before enabling automated payout, split collection, or marketplace resale. The approval evidence MUST verify a provider sandbox for collection, webhook verification, refund, beneficiary/KYC onboarding, payout submission, payout-status retrieval, and idempotent retries; it MUST also identify the accountable operating owner and exception process for payment and KYC events.

#### Scenario: All required PSP capabilities are verified
- **WHEN** the provider sandbox and operating runbook demonstrate every required capability
- **THEN** the readiness record SHALL be approved and finance-dependent phases may proceed

#### Scenario: A required PSP capability is unavailable
- **WHEN** the provider cannot demonstrate a required capability or an accountable operating owner is absent
- **THEN** the readiness record SHALL be rejected and the affected finance feature SHALL remain disabled

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
Completion SHALL require a traceable acceptance matrix for account/access, venue/court, pricing/promotion, booking, payment/finance, on-site operations and customer/community. Each area SHALL include persisted success after reload, invalid input, authorization/tenant isolation and relevant retry/conflict evidence. Provider-dependent capabilities SHALL retain existing go-no-go gates and SHALL NOT be declared operational without provider evidence.

#### Scenario: Full acceptance
- **WHEN** all seven areas pass against real test APIs and an isolated database
- **THEN** the release records results, environment and links to tests

#### Scenario: Mock-only flow
- **WHEN** a required flow passes only with business API mocks or lacks provider evidence
- **THEN** the area remains incomplete or explicitly blocked

### Requirement: Independent package rollout
Each delivery package SHALL have independently reversible activation and recorded entry/exit evidence. Rollback SHALL preserve completed payments, attendance and historical records and SHALL NOT restore false-success paths.

#### Scenario: Package rollback
- **WHEN** a POS integrity failure is found in pilot
- **THEN** new affected operations are disabled without deleting committed receipts or journals
