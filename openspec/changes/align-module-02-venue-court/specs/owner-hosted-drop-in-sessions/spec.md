## MODIFIED Requirements

### Requirement: Preserve existing booking and payment behavior
Post-session court/shuttlecock equal-share bills SHALL remain informational and separate from advance booking split-payment confirmation and generic activities. Finalizing such a bill MUST NOT create deposits, payment receipts, wallet movements, automatic collections, debt balances or host shortfall guarantees. Estimates SHALL be labelled. This separation SHALL preserve the direct-owner booking, final-booking and POS contracts and SHALL NOT reinstate wallet-only payment or add customer cancellation, refunds or rescheduling.

#### Scenario: Existing advance split booking
- **WHEN** a user follows advance split collection
- **THEN** verified share obligations SHALL use the direct-owner payment contract, not a post-session approximate headcount bill

#### Scenario: Final bill is not payment
- **WHEN** the host finalizes actual court/shuttlecock costs
- **THEN** the bill SHALL persist without marking funds collected or adding ancillary purchases
