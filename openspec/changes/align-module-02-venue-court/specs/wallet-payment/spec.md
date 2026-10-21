## MODIFIED Requirements

### Requirement: Legacy wallet conservation during booking changes
For historical wallet records, the platform SHALL preserve original journals/balances and reject unauthorized replay of retired operations. Direct-owner bookings SHALL NOT be refunded through the platform, customers SHALL NOT cancel or reschedule them, and the organization owner or staff assigned to the booking's venue SHALL cancel, move or change the time of one only through the commands of "Operator handling of affected bookings", which move no money, so a confirmed direct booking SHALL never produce wallet credits/debits or refund obligations. Independently authorized legacy compensation SHALL reference original allocations, never retroactively recalculate or convert direct receipts.

#### Scenario: Direct cancellation
- **WHEN** a customer attempts to cancel a post-cutover direct booking, or an owner or assigned staff member cancels one that has an open affected-booking case
- **THEN** the customer's request SHALL be rejected, and neither SHALL create a wallet entry or refund obligation

#### Scenario: Direct reschedule
- **WHEN** a customer attempts to reschedule a post-cutover direct booking, or an owner or assigned staff member moves or changes the time of one that has an open affected-booking case
- **THEN** the customer's request SHALL be rejected, and neither SHALL create a wallet entry, payment obligation or refund obligation

#### Scenario: Historical refund viewed
- **WHEN** old wallet history includes a refund or pending legacy obligation
- **THEN** it SHALL remain accurately visible without automatic repayment or deletion

#### Scenario: Unpaid expiry
- **WHEN** an unpaid direct hold expires
- **THEN** inventory SHALL release without wallet movement
