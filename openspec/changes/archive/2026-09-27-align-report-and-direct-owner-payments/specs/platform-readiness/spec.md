## MODIFIED Requirements

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

## ADDED Requirements

### Requirement: Non-destructive direct-payment cutover
Rollout SHALL inventory and reconcile wallet balances, pending deposits, bookings, entitlements, refunds, transfers, journals and payouts before changing each cohort. Historical records SHALL retain explicit payment-model identity and original references; no automatic conversion, deletion, retroactive terms or transfer of funds is authorized. Legacy liability disposition SHALL be reviewed by the accountable owner before cohort activation. Rollback SHALL stop new affected commands while preserving committed receipts, access to obligations and event recovery; it MUST NOT re-enable false-success or silently restore commission/custody for direct receipts.

#### Scenario: Outstanding balance
- **WHEN** a candidate cohort has unexplained wallet or pending payout liabilities
- **THEN** activation SHALL be blocked until a recorded disposition and reconciliation exists

#### Scenario: Rollback after direct receipt
- **WHEN** an incident occurs after a direct owner payment was received
- **THEN** new collection SHALL pause while receipt history, servicing/refund access and reconciliation remain available without reinterpreting the money as platform-held
