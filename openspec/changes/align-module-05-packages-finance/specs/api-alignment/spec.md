## MODIFIED Requirements

### Requirement: Report-aligned end-to-end contracts
Actual frontend/backend paths, methods, payload fields, upload names/types, enum values, consent versions and response structures SHALL agree for partner drafts/evidence/submission/corrections, venue packages, venue setup and every affected business journey. Server-side request handlers SHALL resolve. Active organization/venue SHALL be explicit rather than hardcoded. Real reloads SHALL restore persisted state and errors SHALL preserve drafts. Data fixtures, local-only calendars, fake paid states and simulated successful exports MUST NOT appear as completed operational outcomes.

#### Scenario: Partner document submission
- **WHEN** an applicant uploads evidence and submits required consents from the actual UI
- **THEN** valid server requests SHALL persist it, and revision_required/pending/history fields SHALL render correctly

#### Scenario: Subscription journey
- **WHEN** an owner registers or renews the package of a venue, reopens its pending payment request or terminates the package
- **THEN** the correct endpoints and fields SHALL return the persisted package status and real payment instructions for AuraCourt's account, and no sandbox or simulated QR SHALL be shown as payable

#### Scenario: Failed split collection
- **WHEN** the API rejects a split/share operation or returns no real group
- **THEN** UI SHALL show error/empty state without sample paid members or success messages

#### Scenario: Organization context
- **WHEN** a manager switches organization and invites staff
- **THEN** the selected scope SHALL be sent and enforced instead of organization 1

#### Scenario: Unsupported export
- **WHEN** an export is unavailable or fails
- **THEN** UI SHALL not claim a generated file; supported exports SHALL provide an actual authorized downloadable artifact
