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
The platform SHALL provide a rehearsed migration plan that backfills each legacy venue into an organization and preserves legacy booking history. Legacy booking responses SHALL stay available until the V1 booking API is retired under the unversioned reservation API requirement. After that, legacy booking history SHALL remain readable through the mapped reservations. The plan MUST include counts/checksums, a compatibility period that ends only through that retirement, and a tested rollback path before pilot data is migrated.

#### Scenario: Migration rehearsal succeeds
- **WHEN** a representative production-like data copy is migrated in rehearsal
- **THEN** the recorded source and target counts/checksums SHALL reconcile and legacy booking history SHALL remain readable

#### Scenario: Rehearsal detects a mismatch
- **WHEN** reconciliation detects a missing or altered legacy record
- **THEN** pilot migration SHALL be blocked and the rollback procedure SHALL restore the prior serving path

#### Scenario: Compatibility period ends
- **WHEN** the V1 booking API is retired and its endpoints are withdrawn
- **THEN** the count of historical bookings SHALL equal the count of their reservation mappings, and every historical booking SHALL remain readable with its original code and amounts and its current status

#### Scenario: Rollback after V1 retirement
- **WHEN** the release that withdrew the V1 booking endpoints and the API version prefix is rolled back
- **THEN** the prior release SHALL serve its versioned paths again from the unchanged historical tables without data restoration, once the OAuth callback and payment webhook URLs point back at those paths, and reservations created in the meantime SHALL remain valid

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
The delivery pipeline SHALL run the backend and frontend static checks on every pipeline, before any image is built. The checks cover code formatting, linting, type analysis, static analysis, architecture conformance and locale key parity. Type analysis covers every shipped source entry point, including the edge worker. Static checks MUST run in check-only mode without modifying files, and MUST NOT depend on a database, cache, queue or other runtime service. Deployment MUST NOT start unless every static check passes. Passing static checks SHALL NOT replace the existing test, provider or rollout gates.

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

### Requirement: Isolated browser acceptance environment
Browser acceptance SHALL execute against an explicitly identified disposable test environment with isolated persisted data, non-demo identities, declared capability cohorts and controlled external integrations. Before any reset, seed or mutation, the runner MUST verify both application and database targets; ambiguous, shared or production targets SHALL fail closed. Reset and cleanup SHALL affect only resources owned by the current run. Test infrastructure MUST NOT expose an unauthenticated production data-reset or payment-confirmation interface.

#### Scenario: Safe repeatable execution
- **WHEN** a developer or CI runner starts browser acceptance against its dedicated test environment
- **THEN** the run SHALL prepare its own data, verify frontend/API/database identity, complete without live provider side effects and release only its owned resources

#### Scenario: Unsafe or mismatched target
- **WHEN** a runtime API override, database connection, cached configuration or existing server resolves outside the approved test environment
- **THEN** the runner SHALL stop before any reset, seed or business mutation and report the mismatched target

#### Scenario: Simulated demo mutation
- **WHEN** a transaction is rolled back by demo simulation
- **THEN** its response SHALL NOT qualify as persistence evidence for browser acceptance

### Requirement: Requirement-level browser coverage evidence
Acceptance SHALL maintain an explicit mapping for all 126 functional requirement identifiers across the seven existing areas, plus separately identified current capabilities outside that inventory. Each entry MUST identify the current specification, user surface or API-only status, actor and scope, priority, test references, execution environment/revision and outcome. Browser, backend and external-provider evidence SHALL be distinguished. Historical passed reports SHALL NOT imply a current browser pass, and missing UI, skipped tests or unexecuted tests MUST remain visible gaps rather than successful coverage.

#### Scenario: Requirement is covered through a shared journey
- **WHEN** one browser journey covers multiple functional requirements
- **THEN** each mapped requirement SHALL cite the assertion or test step demonstrating its own observable outcome, not merely the shared page title

#### Scenario: Requirement has no user interface
- **WHEN** investigation confirms that a required operation is API-only or its intended interface is missing
- **THEN** the matrix SHALL record that disposition and existing backend evidence separately, without inventing browser coverage or silently building a new product interface

### Requirement: Persisted and authorized browser journeys
Critical acceptance SHALL exercise user actions through the interface and real application API, verify durable results after reload and prove relevant role, organization and sibling-venue restrictions. Coverage MUST include authentication, per-court availability, booking/payment/expiry/conflict, promotions and points, booking changes, POS/shift accounting and attendance/service tabs. Extended acceptance SHALL cover partner/subscription/publication/staff workflows, recurrence/split/transfer, inventory, community/tournaments, disputes and reporting. Negative commands SHALL retain valid input where applicable and MUST NOT produce false success, duplicate business effects or unauthorized data disclosure.

#### Scenario: Critical mutation succeeds
- **WHEN** a customer completes a booking or a cashier completes an order through the interface
- **THEN** a subsequent reload and authoritative read SHALL agree on identity, status, amounts and relevant inventory, with one persisted business outcome

#### Scenario: Competing users or repeated command
- **WHEN** two customers attempt the same court-time or an uncertain successful operation is retried
- **THEN** acceptance SHALL demonstrate exclusive inventory and a single business effect while the losing or pending interface reports the actual outcome

#### Scenario: Unauthorized actor
- **WHEN** a cashier attempts platform administration, an owner targets another organization or a manager targets an unassigned sibling venue
- **THEN** both interface access and relevant direct requests SHALL enforce the current authorization contract without revealing foreign data

### Requirement: Truthful payment acceptance boundaries
Browser evidence for direct-owner cohorts SHALL use receiver-bound pending obligations and server-verified receipt processing, separate platform subscription collection, owner-executed refund obligations and immutable legacy-wallet history. Local synthetic provider events MUST be labeled as isolated application-contract evidence, never operational provider certification. Browser return flags, screenshots, optimistic UI or mocked success responses MUST NOT qualify as paid/returned money. Any external provider certification requiring real access SHALL remain an independently gated activity.

#### Scenario: Verified test receipt
- **WHEN** a correctly authenticated synthetic incoming event is delivered to the real isolated receipt-processing boundary
- **THEN** acceptance SHALL verify one matching obligation transitions correctly and duplicate delivery creates no second effect, with the evidence marked synthetic-provider

#### Scenario: Wrong late or client-claimed receipt
- **WHEN** payment evidence has the wrong receiver/amount/reference, arrives after expiry or consists only of a client success claim
- **THEN** acceptance SHALL verify no false booking confirmation or subscription activation and the applicable reconciliation or refund-obligation outcome

#### Scenario: Direct booking refund
- **WHEN** a paid direct booking is cancelled or changed to a lower amount
- **THEN** acceptance SHALL distinguish a refund obligation, owner-reported execution and verified or recipient-acknowledged completion, without automatic legacy-wallet credit or fabricated bank transfer

### Requirement: Repeatable browser diagnostics and experience coverage
Browser tests SHALL run independently with isolated mutable data, controlled venue-local date assumptions and condition-based synchronization. Failures MUST retain enough restricted diagnostic evidence to identify the affected action without publishing credentials or authentication state. Extended checks SHALL cover supported secondary browsers and mobile layouts, Vietnamese/English, light/dark themes, keyboard/modal focus, feature-disabled and network/session-error states, and cross-session updates. Synthetic error tests SHALL remain distinguishable from real-backend success tests.

#### Scenario: Independent rerun
- **WHEN** a test is executed alone, in a full suite or again after failure
- **THEN** its expected result SHALL not depend on another test's data or on a fixed demo balance, court identifier or calendar date

#### Scenario: Failure or degraded service
- **WHEN** a request fails, a session expires or a capability is unavailable
- **THEN** the interface SHALL show truthful localized feedback without claiming success, and the test SHALL retain diagnostic artifacts and assert applicable input/focus recovery

### Requirement: Priority-based browser delivery gates
The delivery pipeline SHALL execute required critical browser acceptance before deployment. A missing environment, failed test, unexpected skip, empty test selection or retry-only pass in the required critical suite MUST NOT yield a successful gate. Full regression SHALL have an explicit pre-release execution path; secondary-browser/mobile checks SHALL be separately identifiable. Reports MUST distinguish passed, failed, flaky, skipped, blocked and not-run outcomes and link to the requirement matrix. Passing isolated tests SHALL NOT authorize production rollout or override existing provider and operating-model gates.

#### Scenario: Critical gate fails
- **WHEN** any required critical journey cannot complete cleanly
- **THEN** the validation job SHALL fail and dependent deployment SHALL not proceed

#### Scenario: Regression evidence is produced
- **WHEN** a pre-release regression finishes
- **THEN** its report SHALL identify source revision, environment, browser, locale/timezone, capability profile and per-requirement outcomes with diagnostic links, without counting skips or retries as clean passes

### Requirement: Manually started delivery pipeline
The delivery pipeline SHALL start only when a maintainer starts it by hand. A push, a merge request or a schedule SHALL NOT start a pipeline, so a push to a deployable branch SHALL NOT deploy anything by itself. A pipeline started on a deployable branch (`main` or `dev`) SHALL run every validation gate and SHALL build and deploy only after all of them pass. A pipeline started on any other branch SHALL run the validation gates only.

#### Scenario: Push to main
- **WHEN** a commit is pushed to `main`
- **THEN** no pipeline SHALL start and production SHALL keep running the previously deployed revision

#### Scenario: Maintainer deploys main
- **WHEN** a maintainer starts the pipeline on `main`
- **THEN** the validation gates SHALL run first, and the images SHALL be built and deployed only if every gate passes

#### Scenario: Maintainer checks another branch
- **WHEN** a maintainer starts the pipeline on a branch other than `main` or `dev`
- **THEN** the validation gates SHALL run and nothing SHALL be built or deployed

### Requirement: Database-enforced record integrity
The data store SHALL enforce three structural rules without relying on application code:
- every persisted lifecycle status value is one of the values defined for that record type;
- every foreign key has an index whose leading columns cover the referencing columns;
- no foreign key cascades a deletion into a financial or audit record.

Three kinds of column MAY be exempt from the status rule, each with a recorded reason: a column that holds a protocol code, a column that preserves a status copied verbatim from a legacy system, and a column that stores a status reported by an external provider as received. An automated schema conformance test SHALL check all three rules against a freshly migrated database. The test MUST fail and name the table and column when a rule is broken.

#### Scenario: Unknown status value
- **WHEN** any writer stores a status value outside the defined set for that record type
- **THEN** the write SHALL be rejected and the existing record SHALL remain unchanged

#### Scenario: New foreign key without an index
- **WHEN** a migration adds a foreign key and no index covers its referencing columns
- **THEN** the schema conformance test SHALL fail, naming the table and constraint

#### Scenario: New status column without a constraint
- **WHEN** a migration adds a lifecycle status column without an allowed-value constraint and without a recorded exemption
- **THEN** the schema conformance test SHALL fail, naming the table and column

#### Scenario: New cascade into financial history
- **WHEN** a migration adds a foreign key that cascades deletion into a financial or audit table
- **THEN** the schema conformance test SHALL fail, naming the constraint

#### Scenario: Conforming schema
- **WHEN** every status column is constrained or exempt, every foreign key is indexed, and no foreign key cascades into financial history
- **THEN** the schema conformance test SHALL pass
