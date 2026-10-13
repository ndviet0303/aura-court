## MODIFIED Requirements

### Requirement: Legacy wallet conservation during booking changes
For historical wallet records, the platform SHALL preserve original journals/balances and reject unauthorized replay of retired operations. Direct-owner bookings SHALL NOT be cancelled, refunded or rescheduled, so a confirmed direct booking SHALL never produce wallet credits/debits or refund obligations. Independently authorized legacy compensation SHALL reference original allocations, never retroactively recalculate or convert direct receipts.

#### Scenario: Direct cancellation
- **WHEN** a client attempts to cancel a post-cutover direct booking
- **THEN** the request SHALL be rejected and no wallet entry or refund obligation SHALL be created

#### Scenario: Direct reschedule
- **WHEN** a client attempts to reschedule a post-cutover direct booking
- **THEN** the request SHALL be rejected and no wallet entry, payment obligation or refund obligation SHALL be created

#### Scenario: Historical refund viewed
- **WHEN** old wallet history includes a refund or pending legacy obligation
- **THEN** it SHALL remain accurately visible without automatic repayment or deletion

#### Scenario: Unpaid expiry
- **WHEN** an unpaid direct hold expires
- **THEN** inventory SHALL release without wallet movement
