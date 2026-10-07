## ADDED Requirements

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
The delivery pipeline SHALL execute required critical browser acceptance on merge requests and before deployment. A missing environment, failed test, unexpected skip, empty test selection or retry-only pass in the required critical suite MUST NOT yield a successful gate. Full regression SHALL have scheduled-pipeline and explicit pre-release execution paths; secondary-browser/mobile checks SHALL be separately identifiable. Reports MUST distinguish passed, failed, flaky, skipped, blocked and not-run outcomes and link to the requirement matrix. Passing isolated tests SHALL NOT authorize production rollout or override existing provider and operating-model gates.

#### Scenario: Critical gate fails
- **WHEN** any required critical journey cannot complete cleanly
- **THEN** the validation job SHALL fail and dependent deployment SHALL not proceed

#### Scenario: Regression evidence is produced
- **WHEN** a scheduled or pre-release regression finishes
- **THEN** its report SHALL identify source revision, environment, browser, locale/timezone, capability profile and per-requirement outcomes with diagnostic links, without counting skips or retries as clean passes
