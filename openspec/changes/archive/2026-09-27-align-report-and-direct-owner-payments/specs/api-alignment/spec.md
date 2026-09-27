## REMOVED Requirements

### Requirement: No cancellation or rescheduling interface
**Reason**: Report alignment restores policy-governed booking changes.
**Migration**: Replace obsolete prohibition copy and controls with authoritative policy preview/request/status; retain historical statuses and reject incompatible old idempotency replay.

### Requirement: Retired cancellation-refund administration
**Reason**: Owner-executed refund tracking replaces blanket retirement; platform administrators do not automatically pay on behalf of owners.
**Migration**: Introduce scoped obligation review/evidence instead of reopening blind approve-and-credit endpoints. Legacy records remain read-only unless explicitly reconciled into a reviewed obligation.

## ADDED Requirements

### Requirement: Report-aligned end-to-end contracts
Actual frontend/backend paths, methods, payload fields, upload names/types, enum values, consent versions and response structures SHALL agree for partner drafts/evidence/submission/corrections, subscriptions, venue setup and every affected business journey. Server-side request handlers SHALL resolve. Active organization/venue SHALL be explicit rather than hardcoded. Real reloads SHALL restore persisted state and errors SHALL preserve drafts. Data fixtures, local-only calendars, fake paid states and simulated successful exports MUST NOT appear as completed operational outcomes.

#### Scenario: Partner document submission
- **WHEN** an applicant uploads evidence and submits required consents from the actual UI
- **THEN** valid server requests SHALL persist it, and revision_required/pending/history fields SHALL render correctly

#### Scenario: Subscription journey
- **WHEN** an owner selects a plan, activates trial or initiates payment
- **THEN** the correct endpoints and fields SHALL return usable persisted status/payment instructions

#### Scenario: Failed split collection
- **WHEN** the API rejects a split/share operation or returns no real group
- **THEN** UI SHALL show error/empty state without sample paid members or success messages

#### Scenario: Organization context
- **WHEN** a manager switches organization and invites staff
- **THEN** the selected scope SHALL be sent and enforced instead of organization 1

#### Scenario: Unsupported export
- **WHEN** an export is unavailable or fails
- **THEN** UI SHALL not claim a generated file; supported exports SHALL provide an actual authorized downloadable artifact

### Requirement: Policy-aware changes and truthful finance interfaces
Customer/owner/staff booking pages and Vietnamese/English policies SHALL show captured change/cancellation terms, fees, owner refund responsibilities and actual request/evidence status. Success SHALL require persisted server confirmation; a dispute judgment or owner-reported transfer SHALL not be labelled verified refund completion. Capability availability SHALL combine permissions, subscriptions, provider readiness and rollout state.

#### Scenario: Cancellation request
- **WHEN** an eligible customer previews and submits a cancellation
- **THEN** the UI SHALL show the accepted policy calculation and request status, not a blanket unsupported message or immediate refund claim

#### Scenario: Provider not ready
- **WHEN** an owner has not verified SePay
- **THEN** online payment SHALL be unavailable with setup guidance, without demo success or a QR to Aura Court's account

