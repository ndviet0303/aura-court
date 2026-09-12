## REMOVED Requirements

### Requirement: Automatic refund to wallet on cancellation
**Reason**: Booking cancellation and rescheduling are no longer supported, so their automatic refund entitlement is removed.
**Migration**: Remove cancellation/refund and rescheduling actions. Retain existing wallet credits and booking history without recomputation; unpaid holds continue to expire automatically without wallet credit. Refunds and compensation outside these retired operations retain their existing contracts.

## ADDED Requirements

### Requirement: No wallet movement from retired booking changes
The platform SHALL NOT create wallet credits, price-difference debits, refund requests or replacement payment records from attempted booking cancellation, partial cancellation or rescheduling. Retiring these operations SHALL NOT delete, reverse or recompute historical wallet transactions or balances. Existing deposit, booking payment, tournament payment and unrelated authorized compensation behavior SHALL remain unchanged.

#### Scenario: Cancel or reschedule attempt leaves wallet untouched
- **WHEN** a client attempts a retired cancellation or rescheduling command, including repeated attempts
- **THEN** the wallet balance and transaction history SHALL remain unchanged and no refund request SHALL be created

#### Scenario: Historical refund remains visible
- **WHEN** a user reloads wallet history containing a cancellation or rescheduling refund issued before retirement
- **THEN** the original amount, transaction reference and recorded balance SHALL remain available without issuing or reversing a credit

#### Scenario: Unpaid hold expires
- **WHEN** an unpaid booking expires automatically
- **THEN** expiry SHALL release eligible inventory without crediting a wallet
