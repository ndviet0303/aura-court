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

### Requirement: Static analysis delivery gate
The delivery pipeline SHALL run the backend and frontend static checks on merge requests and on every pipeline that can deploy, before any image is built. The checks cover code formatting, linting, type analysis, static analysis, architecture conformance and locale key parity. Type analysis covers every shipped source entry point, including the edge worker. Static checks MUST run in check-only mode without modifying files, and MUST NOT depend on a database, cache, queue or other runtime service. Deployment MUST NOT start unless every static check passes. Passing static checks SHALL NOT replace the existing test, provider or rollout gates.

#### Scenario: New violation blocks delivery
- **WHEN** a change introduces a formatting deviation, lint violation, type error, static-analysis finding or architecture violation that is not recorded in a debt baseline
- **THEN** the validation job SHALL fail naming the file and rule, and no image SHALL be built or deployed

#### Scenario: Missing translation key
- **WHEN** a message key exists in one supported locale but not in the other
- **THEN** the validation job SHALL fail and name the missing key

#### Scenario: Edge worker type error
- **WHEN** the edge worker source contains a type error
- **THEN** the validation job SHALL fail even though the browser bundle still builds

#### Scenario: Runner without runtime services
- **WHEN** the static checks run where no database, cache or queue service is reachable
- **THEN** they SHALL produce the same result as on a developer machine

### Requirement: Shrinking debt baselines
Violations that exist when a check is introduced SHALL be recorded in versioned baseline files that the check reads. This lets the check block new violations before all existing debt is fixed. Each check MUST fail when a violation is not covered by its baseline, including one more violation of an already recorded kind in the same file. Each check MUST also fail when a baseline entry no longer matches a violation, so that fixed debt leaves the baseline in the same change. Baselines MUST only be written by an explicit, documented regeneration command and MUST NOT change as a side effect of running a check.

#### Scenario: Only recorded debt remains
- **WHEN** the code contains only violations recorded in the baselines
- **THEN** the static checks SHALL pass

#### Scenario: Recorded file gains a violation
- **WHEN** a file with recorded violations gains one more violation of the same rule
- **THEN** the check SHALL fail although the file already appears in the baseline

#### Scenario: Fixed debt left in the baseline
- **WHEN** a change fixes a recorded violation but keeps its baseline entry
- **THEN** the check SHALL fail and identify the stale entry until it is removed

### Requirement: Repository-wide architecture conformance
Architecture rules SHALL apply to every backend class and every frontend view, component and route in the repository, not to a selected set of features. The enforced limits are:

- Backend classes have at most 300 lines.
- Controllers have at most 7 public methods, with no inline request validation, direct storage access or direct model queries.
- Domain actions have no HTTP request dependency and no direct model queries.
- DTOs are immutable.
- Frontend views have at most 250 lines, of which at most 200 are script.
- Components have at most 400 lines.
- Views and components do not import the raw API client or legacy stores.
- No new legacy stores are added.
- Routes are loaded lazily.

Each file that violates a rule when this requirement is introduced SHALL be listed in an allowlist with its measured value as a ceiling. The documented architecture conventions MUST state the same limits that the checks enforce.

#### Scenario: New violation outside the allowlist
- **WHEN** a file that is not allowlisted for a rule violates that rule, for example a component that grows from 400 to 401 lines
- **THEN** the architecture check SHALL fail naming the file, rule, measured value and limit

#### Scenario: Allowlisted file grows
- **WHEN** an allowlisted file's measured value rises above its recorded ceiling
- **THEN** the architecture check SHALL fail

#### Scenario: Allowlisted file is fixed
- **WHEN** an allowlisted file no longer exceeds the rule's limit
- **THEN** the architecture check SHALL fail until its allowlist entry is removed

### Requirement: Crash-class findings are fixed, not baselined
Some findings mean that code fails, or silently misbehaves, whenever it runs. This covers findings at the backend analyzer's basic tier and frontend template parse errors. Basic-tier findings include references to unknown classes, functions, constants or console options, wrong argument counts, always-undefined variables and environment reads outside configuration. These findings MUST be fixed and MUST NOT be recorded in a debt baseline. A finding verified as a false positive SHALL be suppressed only at the reported line, by its identifier, with the reason stated in the suppression.

#### Scenario: Basic tier is clean
- **WHEN** the backend analyzer runs at its basic tier without any debt baseline
- **THEN** it SHALL report no findings

#### Scenario: Suppression without a reason
- **WHEN** a line-level suppression omits its reason
- **THEN** the static analysis check SHALL fail

#### Scenario: Unparseable template
- **WHEN** a component template cannot be parsed, for example because an element is never closed
- **THEN** the frontend static checks SHALL fail before the bundle is built
