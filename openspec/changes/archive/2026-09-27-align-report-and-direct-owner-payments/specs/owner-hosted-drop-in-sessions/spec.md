## MODIFIED Requirements

### Requirement: Facility-owner hosted sessions
A facility owner SHALL open and manage a badminton session on their own court with start/end, capacity, category and accepted skill labels. The event SHALL obtain an authoritative court-time allocation that excludes subsequent booking/recurrence/tournament allocations; the same inventory checks SHALL protect both directions. Only the hosting owner with current venue authority SHALL manage the session and its post-session bill. Owner hosting SHALL not imply a paid booking or add the host as a participant. Customer-hosted events using their own entitlements SHALL use the separate generic activity contract.

#### Scenario: Owner opens a session
- **WHEN** a valid owner opens an available court-time session
- **THEN** one exclusive allocation SHALL be recorded without a payment claim or automatic host participation

#### Scenario: Later booking
- **WHEN** a customer tries to book overlapping time after session publication
- **THEN** booking SHALL fail with a conflict

#### Scenario: Unauthorized management
- **WHEN** an unrelated customer/staff/owner tries to manage this owner's drop-in bill
- **THEN** the action SHALL be denied without affecting separately authorized customer-hosted activities

### Requirement: Preserve existing booking and payment behavior
Post-session court/shuttlecock equal-share bills SHALL remain informational and separate from advance booking split-payment confirmation and generic activities. Finalizing such a bill MUST NOT create deposits, payment receipts, wallet movements, automatic collections, debt balances or host shortfall guarantees. Estimates SHALL be labelled. This separation SHALL preserve the new direct-owner booking, policy-governed refund and POS contracts rather than reinstate old wallet-only or no-cancellation behavior.

#### Scenario: Existing advance split booking
- **WHEN** a user follows advance split collection
- **THEN** verified share obligations SHALL use the direct-owner payment contract, not a post-session approximate headcount bill

#### Scenario: Final bill is not payment
- **WHEN** the host finalizes actual court/shuttlecock costs
- **THEN** the bill SHALL persist without marking funds collected or adding ancillary purchases
